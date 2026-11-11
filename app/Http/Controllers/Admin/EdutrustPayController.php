<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdutrustPayOutbox;
use App\Models\EdutrustPaySetting;
use App\Services\EdutrustPay\SchedulerPulse;
use App\Services\EdutrustPay\SettingsResolver;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\ContractVersion;
use EdutrustPay\Contract\Signer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * The EdutrustPay connection settings screen.
 *
 * WHAT THIS SCREEN IS FOR: a bursar receives three values from the body's
 * operator — an institution reference, a key id and a secret — and needs to put
 * them somewhere. Before this existed that meant editing .env and running an
 * artisan command, which is not something to ask of the person who actually
 * holds the credentials.
 *
 * WHAT IT DELIBERATELY DOES NOT DO:
 *
 *   - It never writes .env. That would need the web user to have write access
 *     to a file holding every secret this application owns.
 *   - It never displays a saved secret. Leaving the field blank keeps the
 *     stored one; the only way to learn it is to have been given it.
 *   - It cannot create an institution on the platform. Credentials are minted by
 *     the body's operator, not requested from here — otherwise EdutrustPay would
 *     have to trust whatever an institution told it about its own identity.
 */
class EdutrustPayController extends Controller
{
    private $title = 'EdutrustPay Reporting';

    private $route = 'admin.edutrustpay';

    private $view = 'admin.edutrustpay';

    public function __construct()
    {
        // Seeing the credentials, changing them and using them are three
        // separate acts: a wrong value here stops reporting with no visible
        // error, and from the console this institution simply goes quiet.
        $this->middleware('permission:edutrustpay-view', ['only' => ['index']]);
        $this->middleware('permission:edutrustpay-update', ['only' => ['update']]);
        $this->middleware('permission:edutrustpay-test', ['only' => ['test', 'sendNow']]);
    }

    public function index(SettingsResolver $resolver)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        $data['setting'] = EdutrustPaySetting::current();

        // Shows whether the values in force come from this screen or from .env,
        // so nobody wonders why something they saved is being ignored.
        $data['resolved'] = $resolver->resolve();

        $data['capabilities'] = (array) config('edutrustpay.capabilities', []);
        $data['notDeclared'] = array_diff(Capability::all(), $data['capabilities']);
        $data['contractVersion'] = ContractVersion::CURRENT;

        // Is it sending? The scheduler, the queue and what the console answered.
        $data['reporting'] = $resolver->isReporting();
        $data['scheduler'] = SchedulerPulse::state();
        $data['schedulerSeen'] = SchedulerPulse::lastSeen();
        $data['schedulerSetup'] = $this->schedulerSetup();
        $data['lastDelivered'] = EdutrustPayOutbox::where('status', EdutrustPayOutbox::DELIVERED)->max('delivered_at');
        $data['waiting'] = EdutrustPayOutbox::where('status', EdutrustPayOutbox::PENDING)->count();
        $data['problems'] = $this->problems();
        $data['recent'] = EdutrustPayOutbox::orderByDesc('updated_at')->orderByDesc('id')->limit(8)->get(self::LIST_COLUMNS);

        return view($this->view . '.index', $data);
    }

    /** Everything but the payload, which can be large. */
    private const LIST_COLUMNS = [
        'id', 'kind', 'period', 'sequence', 'status', 'attempts', 'next_attempt_at',
        'delivered_at', 'last_status_code', 'last_response', 'created_at', 'updated_at',
    ];

    /**
     * Send everything now: the same jobs the scheduler runs each night, run on
     * request, with what each one said.
     *
     * For checking that reporting works without waiting for 03:00, and for
     * pushing a month out at once after a correction. It does not replace the
     * scheduler: nothing goes out again until someone presses this again, or
     * the scheduler is set up.
     */
    public function sendNow(SettingsResolver $resolver)
    {
        if (! $resolver->isReporting()) {
            return back()->with('error', 'Nothing was sent: reporting is switched off or the connection details are incomplete. Fill them in, tick "Send reports for this institution" and save.');
        }

        @set_time_limit(600);
        $started = now();

        // Someone pressing this wants everything tried now, not after the
        // back-off wait. Items the console refused are tried once more too,
        // unless a later version of the same month has already gone through.
        EdutrustPayOutbox::where('status', EdutrustPayOutbox::PENDING)->update(['next_attempt_at' => null]);
        EdutrustPayOutbox::whereIn('id', $this->problems()->pluck('id'))
            ->update(['status' => EdutrustPayOutbox::PENDING, 'attempts' => 0, 'next_attempt_at' => null]);

        $steps = [
            ['Monthly reports, last 3 months', 'edutrustpay:report', ['--months' => 3]],
            ['Daybooks, last 3 months', 'edutrustpay:daybook', ['--months' => 3]],
            ['Budget sheets, last 3 months', 'edutrustpay:budget-sheet', ['--months' => 3]],
            ['Check-in', 'edutrustpay:heartbeat', []],
            ['Delivery to the console', 'edutrustpay:flush', []],
        ];

        $results = [];

        foreach ($steps as [$label, $command, $arguments]) {
            try {
                $code = Artisan::call($command, $arguments);
                $output = Artisan::output();
            } catch (\Throwable $e) {
                $code = 1;
                $output = $e->getMessage();
            }

            $results[] = [
                'label' => $label,
                'ok' => $code === 0,
                'output' => mb_substr(trim(preg_replace('/\e\[[\d;]*m/', '', (string) $output)), -4000),
            ];
        }

        $delivered = EdutrustPayOutbox::where('status', EdutrustPayOutbox::DELIVERED)->where('delivered_at', '>=', $started)->get(['kind', 'period']);
        $waiting = EdutrustPayOutbox::where('status', EdutrustPayOutbox::PENDING)->orderBy('id')->get(self::LIST_COLUMNS);
        $problems = $this->problems();

        // The delivery job itself succeeds when it merely defers; say plainly
        // that nothing got through.
        if ($waiting->isNotEmpty() || $problems->isNotEmpty()) {
            $results[array_key_last($results)]['ok'] = false;
        }

        $stepFailed = collect($results)->contains('ok', false);

        if ($waiting->isNotEmpty()) {
            $message = 'The console could not be reached: ' . $waiting->count() . ' item(s) are waiting here. ' . self::explain($waiting->first());
        } elseif ($problems->isNotEmpty()) {
            $message = 'The console refused ' . $problems->count() . ' item(s): ' . self::explain($problems->first());
        } elseif ($stepFailed) {
            $message = 'Something went wrong while preparing the reports. See the steps below.';
        } else {
            $sent = $delivered->groupBy('kind')->map->count()
                ->map(fn ($n, $kind) => $n . ' ' . strtolower(self::kindLabel($kind)) . ($n > 1 ? 's' : ''))
                ->implode(', ');

            $message = 'Done. Delivered to the console: ' . ($sent ?: 'nothing new') . '. Months that had not changed since they were last sent were not sent again; the console already has them.';
        }

        $ok = $waiting->isEmpty() && $problems->isEmpty() && ! $stepFailed;

        return back()
            ->with($ok ? 'success' : 'error', $message)
            ->with('etp_run', ['ok' => $ok, 'message' => $message, 'at' => $started->toDateTimeString(), 'steps' => $results]);
    }

    /**
     * Items the console refused that still matter: those not since replaced by
     * a later version of the same month that did go through.
     */
    private function problems()
    {
        $deliveredUpTo = EdutrustPayOutbox::where('status', EdutrustPayOutbox::DELIVERED)
            ->selectRaw('kind, period, MAX(sequence) as seq')
            ->groupBy('kind', 'period')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->kind . '|' . $row->period => (int) $row->seq]);

        // A check-in has no versions: a failed one is replaced only by a check-in
        // delivered after it, not by an older one that went through.
        $lastCheckIn = EdutrustPayOutbox::where('kind', 'heartbeat')->where('status', EdutrustPayOutbox::DELIVERED)->max('delivered_at');

        return EdutrustPayOutbox::where('status', EdutrustPayOutbox::FAILED)
            ->orderBy('id')
            ->get(self::LIST_COLUMNS)
            ->filter(fn ($row) => $row->kind === 'heartbeat'
                ? ! $lastCheckIn || \Carbon\Carbon::parse($lastCheckIn)->lt($row->updated_at)
                : ($deliveredUpTo[$row->kind . '|' . $row->period] ?? 0) < $row->sequence)
            ->values();
    }

    public static function kindLabel(?string $kind): string
    {
        return [
            'report' => 'Monthly report',
            'daybook' => 'Daybook',
            'budget_sheet' => 'Budget sheet',
            'heartbeat' => 'Check-in',
        ][$kind] ?? ucfirst(str_replace('_', ' ', (string) $kind));
    }

    /**
     * What the console said about an item, in a sentence.
     */
    public static function explain($item): string
    {
        if ($item->status === EdutrustPayOutbox::DELIVERED) {
            return 'Received by the console.';
        }

        if ($item->last_status_code === null) {
            return $item->attempts > 0
                ? 'No answer from the console (no internet, or the console address is wrong). It stays here and is tried again on the next delivery, so nothing is lost.'
                : 'Not tried yet.';
        }

        // The console's own words: its message, else its list of errors,
        // else just the error code.
        $decoded = json_decode((string) $item->last_response, true);
        $said = null;

        if (is_array($decoded)) {
            $said = $decoded['message'] ?? null;

            if (! $said && ! empty($decoded['errors'])) {
                $said = implode(' ', array_slice(\Illuminate\Support\Arr::flatten((array) $decoded['errors']), 0, 3));
            }

            $said = $said ?: ($decoded['error'] ?? null);

            if (($decoded['error'] ?? null) === 'unsupported_contract_version') {
                $said .= ' The console is older than this system: ask whoever runs the console to update it, then send again.';
            }
        }

        if ($item->last_status_code === 401) {
            $said = 'The key id or secret is wrong, or the key was replaced on the console.';
        }

        // The console always answers in JSON. A web page means something else
        // answered: the address is wrong, or points at another website.
        if (! is_array($decoded) && str_contains(substr(ltrim((string) $item->last_response), 0, 200), '<')) {
            $address = app(SettingsResolver::class)->resolve()['endpoint'] ?? 'the console address';

            return 'HTTP ' . $item->last_status_code . ': ' . (in_array((int) $item->last_status_code, [404, 405], true)
                ? 'Nothing at ' . $address . ' answers as the EdutrustPay console: another website replied. Check the Console address below. It must be the console\'s own address, such as https://edutrustpay.innovakickstarter.com, with nothing after it, and the key must have been issued on that same console.'
                : $address . ' answered with a web page instead of the EdutrustPay console. Check the Console address below.');
        }

        return 'HTTP ' . $item->last_status_code . ': ' . mb_substr((string) ($said ?: $item->last_response), 0, 300);
    }

    /**
     * How to start the scheduler on this computer, with its real paths.
     */
    private function schedulerSetup(): array
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $php = $this->phpPath($windows);
        $base = base_path();

        return [
            'windows' => $windows,
            'command' => $windows
                ? 'schtasks /Create /SC MINUTE /MO 1 /RU SYSTEM /TN "EdutrustPay scheduler (' . basename($base) . ')" /TR "\"' . $php . '\" \"' . $base . '\\artisan\" schedule:run"'
                : '* * * * * cd ' . $base . ' && ' . $php . ' artisan schedule:run >> /dev/null 2>&1',
        ];
    }

    private function phpPath(bool $windows): string
    {
        $binary = (string) PHP_BINARY;
        $name = strtolower(basename($binary));

        if ($name === 'php' || $name === 'php.exe') {
            return $binary;
        }

        // On LiteSpeed (lsphp, as on Hostinger) or CGI hosting, PHP_BINARY is
        // the web server's PHP. Cron needs the command-line php, which those
        // hosts install beside it; open_basedir often hides it from is_file(),
        // so for lsphp it is named without looking.
        if ($binary !== '' && preg_match('/^(lsphp|php-cgi)/', $name)) {
            $sibling = dirname($binary).DIRECTORY_SEPARATOR.($windows ? 'php.exe' : 'php');

            if (str_starts_with($name, 'lsphp') || @is_file($sibling)) {
                return $sibling;
            }
        }

        // Under Apache or PHP-FPM, PHP_BINARY is the web server, not php.
        $ini = php_ini_loaded_file();
        $candidate = $ini ? dirname($ini) . DIRECTORY_SEPARATOR . ($windows ? 'php.exe' : 'php') : null;

        if ($candidate && is_file($candidate)) {
            return $candidate;
        }

        return $windows ? 'php.exe' : 'php';
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'endpoint' => ['required_with:key_id', 'nullable', 'url', 'max:191'],
            'institution_ref' => ['nullable', 'string', 'max:64'],
            'key_id' => ['nullable', 'string', 'max:64'],
            // Blank means "leave the stored one alone" — see below.
            'secret' => ['nullable', 'string', 'min:16', 'max:191'],
        ], [], [
            'institution_ref' => 'institution reference',
            'key_id' => 'key id',
        ]);

        $setting = EdutrustPaySetting::current();

        $setting->fill([
            'enabled' => $request->boolean('enabled'),
            'endpoint' => $data['endpoint'] ?? null,
            'institution_ref' => $data['institution_ref'] ?? null,
            'key_id' => $data['key_id'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        /*
         * A blank secret field means "keep what is stored", not "erase it".
         *
         * The field is always blank on load, because a saved secret is never
         * displayed. Treating blank as an instruction to clear would wipe a
         * working credential every time somebody corrected a typo in the
         * endpoint — and this institution would go silent without anyone
         * touching the thing that mattered.
         */
        if (filled($data['secret'] ?? null)) {
            $setting->secret_ciphertext = $data['secret'];

            // A new secret invalidates whatever the last test proved.
            $setting->last_tested_at = null;
            $setting->last_test_ok = null;
            $setting->last_test_message = null;
        }

        $setting->save();

        return redirect()
            ->route($this->route . '.index')
            ->with('success', 'Settings saved. Use "Test connection" to confirm the credentials work.');
    }

    /**
     * Prove the credentials work, from here, now.
     *
     * A heartbeat rather than a report: it carries no figures, is signed exactly
     * as a report is, and is a useful thing to have sent anyway — it tells the
     * body this institution is alive.
     *
     * This catches the most likely real failure, which is a key rotated on the
     * platform and never updated here. Nothing else notices until month end,
     * when the reports start bouncing.
     */
    public function test(SettingsResolver $resolver)
    {
        $settings = $resolver->resolve();

        if ($settings === null) {
            return back()->with('error', 'Nothing to test yet — set the console address, institution reference, key id and secret first.');
        }

        $body = json_encode(['note' => 'Connection test from the institution settings screen.']);
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $response = Http::withHeaders([
                'X-Edutrust-Key-Id' => $settings['key_id'],
                'X-Edutrust-Timestamp' => $timestamp,
                'X-Edutrust-Signature' => Signer::signRaw($body, $timestamp, $settings['secret']),
                'Accept' => 'application/json',
            ])->withBody($body, 'application/json')
                ->timeout(15)
                ->post(rtrim($settings['endpoint'], '/') . '/api/v1/heartbeat');
        } catch (\Throwable $e) {
            return $this->recordTest(false, 'Could not reach ' . $settings['endpoint'] . '. Reports queue locally and retry, so this is not necessarily a problem with the credentials.');
        }

        if ($response->successful()) {
            return $this->recordTest(true, 'Accepted by ' . $settings['endpoint'] . '. A heartbeat was recorded against this institution.');
        }

        if ($response->status() === 401) {
            /*
             * The console answers an unknown key and a bad signature
             * identically, on purpose, so live key ids cannot be enumerated.
             * Say what to check rather than guessing which it was.
             */
            return $this->recordTest(false, 'Rejected. The key id or secret is wrong, or the key has been rotated or revoked on the platform. Ask the operator to reissue and paste the new values here.');
        }

        return $this->recordTest(false, 'Unexpected response (HTTP ' . $response->status() . ') from ' . $settings['endpoint'] . '.');
    }

    private function recordTest(bool $ok, string $message)
    {
        $setting = EdutrustPaySetting::current();

        $setting->fill([
            'last_tested_at' => now(),
            'last_test_ok' => $ok,
            'last_test_message' => mb_substr($message, 0, 500),
        ])->save();

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
