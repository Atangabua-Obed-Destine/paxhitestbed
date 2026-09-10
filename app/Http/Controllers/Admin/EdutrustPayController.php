<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdutrustPaySetting;
use App\Services\EdutrustPay\SettingsResolver;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\ContractVersion;
use EdutrustPay\Contract\Signer;
use Illuminate\Http\Request;
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
        $this->middleware('permission:edutrustpay-test', ['only' => ['test']]);
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

        return view($this->view . '.index', $data);
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
