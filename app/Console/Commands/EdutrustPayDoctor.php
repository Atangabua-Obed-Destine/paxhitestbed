<?php

namespace App\Console\Commands;

use App\Services\EdutrustPay\LedgerSummaryService;
use App\Services\EdutrustPay\PeriodReportBuilder;
use Carbon\Carbon;
use EdutrustPay\Contract\Canonical;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\ContractVersion;
use EdutrustPay\Contract\Signer;
use EdutrustPay\Contract\Validator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Checks this institution can report before anyone waits for month end to
 * discover it cannot.
 *
 * Three things get verified, in the order they fail in practice:
 *
 *   1. THE CONTRACT ITSELF. The shared package is re-signed against its
 *      published test vectors here. If this codebase's serialisation has drifted
 *      by a single byte, every report will be rejected as a bad signature and
 *      the message will send everyone hunting for a key problem that does not
 *      exist. This is the check that turns that into one line of output.
 *
 *   2. WHAT THIS SYSTEM CAN ACTUALLY PRODUCE. Every declared capability is
 *      computed for a real month. A capability that is declared but cannot be
 *      computed would otherwise mean either a failed push or, far worse, a zero
 *      standing in for a figure nobody can measure.
 *
 *   3. WHETHER THE ENDPOINT IS THERE. Last, because it is the only one somebody
 *      can fix by waiting.
 *
 * Written as a command rather than a test on purpose: this repository's test
 * suite does not currently run (14 of its 16 tests error before any of this was
 * added), and a check nobody can execute is not a check.
 */
class EdutrustPayDoctor extends Command
{
    protected $signature = 'edutrustpay:doctor {period? : Month to test against. Defaults to last month.}';

    protected $description = 'Verify this institution can build and sign a valid EdutrustPay report.';

    private int $problems = 0;

    public function handle(PeriodReportBuilder $builder, Validator $validator): int
    {
        $period = $this->argument('period') ?: Carbon::now()->subMonth()->format('Y-m');

        $this->line('<options=bold>EdutrustPay client check</> — contract v'.ContractVersion::CURRENT);
        $this->newLine();

        $this->checkContractVectors();
        $this->checkConfiguration();
        $this->checkLedger($period);
        $this->checkPayload($builder, $validator, $period);
        $this->checkEndpoint();

        $this->newLine();

        if ($this->problems === 0) {
            $this->info('All checks passed. This institution can report.');

            return self::SUCCESS;
        }

        $this->error($this->problems.' problem(s) found. Reporting will not work correctly until they are fixed.');

        return self::FAILURE;
    }

    /**
     * Re-sign the published fixtures here and compare with their pinned values.
     */
    private function checkContractVectors(): void
    {
        $dir = base_path('vendor/edutrustpay/reporting-contract/fixtures');

        if (! is_dir($dir)) {
            $this->fail('Contract fixtures', 'The reporting-contract package is not installed.');

            return;
        }

        $index = json_decode((string) file_get_contents($dir.'/index.json'), true) ?: [];
        $drifted = [];

        foreach (array_keys($index) as $name) {
            $fixture = json_decode((string) file_get_contents($dir.'/'.$name.'.json'), true);

            // Canonicalise from the decoded payload, not the stored string, so
            // this genuinely exercises THIS codebase's serialisation.
            $recanonicalised = Canonical::encode(Canonical::decode($fixture['canonical_json']));
            $signature = Signer::signRaw($recanonicalised, $fixture['signing']['timestamp'], $fixture['signing']['secret']);

            if ($signature !== $fixture['signing']['signature']) {
                $drifted[] = $name;
            }
        }

        $drifted === []
            ? $this->pass('Contract vectors', count($index).' fixtures re-sign to their published signatures')
            : $this->fail('Contract vectors', 'Serialisation has drifted: '.implode(', ', $drifted));
    }

    private function checkConfiguration(): void
    {
        $missing = [];

        foreach (['endpoint', 'key_id', 'secret', 'institution_ref'] as $key) {
            if (blank(config('edutrustpay.'.$key))) {
                $missing[] = 'EDUTRUSTPAY_'.strtoupper($key);
            }
        }

        $missing === []
            ? $this->pass('Configuration', 'endpoint, key id, secret and institution reference are set')
            : $this->fail('Configuration', 'Missing: '.implode(', ', $missing));

        $declared = (array) config('edutrustpay.capabilities', []);
        $unknown = array_diff($declared, Capability::all());

        if ($unknown !== []) {
            $this->fail('Capabilities', 'Not recognised by the contract: '.implode(', ', $unknown));
        } else {
            $this->pass('Capabilities', 'declaring '.implode(', ', $declared));
            $this->note('not declared: '.(implode(', ', array_diff(Capability::all(), $declared)) ?: 'nothing'));
        }

        if (! config('edutrustpay.enabled')) {
            $this->note('EDUTRUSTPAY_ENABLED is false — reports will be built on request but nothing is scheduled.');
        }
    }

    private function checkLedger(string $period): void
    {
        try {
            $ledger = new LedgerSummaryService($period);
            $control = $ledger->control();
        } catch (\Throwable $e) {
            $this->fail('Ledger', $e->getMessage());

            return;
        }

        $balanced = $control['total_debits']->equals($control['total_credits']);

        $balanced
            ? $this->pass('Ledger', sprintf(
                '%s balances: %s across %d entries',
                $period,
                $control['total_debits']->value(),
                $control['journal_entry_count']
            ))
            : $this->warn2('Ledger', sprintf(
                '%s does NOT balance: debits %s against credits %s. This will be reported and will raise a '
                .'finding — which is correct. Fix the books, not the report.',
                $period,
                $control['total_debits']->value(),
                $control['total_credits']->value()
            ));

        if ($control['unposted_count'] > 0) {
            $this->note($control['unposted_count'].' unposted entries in '.$period.' are excluded from every figure.');
        }
    }

    private function checkPayload(PeriodReportBuilder $builder, Validator $validator, string $period): void
    {
        try {
            $payload = $builder->build($period);
        } catch (\Throwable $e) {
            $this->fail('Payload', 'Could not build '.$period.': '.$e->getMessage());

            return;
        }

        $result = $validator->validate($payload);

        if (! $result['valid']) {
            $this->fail('Payload', 'Invalid: '.implode('; ', $result['errors']));

            return;
        }

        $this->pass('Payload', $period.' builds and validates ('.strlen(Canonical::encode($payload)).' bytes)');

        foreach ($result['warnings'] as $warning) {
            // Warnings are not errors. They are exactly what the console needs
            // to be told, and the report is sent regardless.
            $this->note('will raise ['.$warning['code'].'] '.$warning['message']);
        }
    }

    private function checkEndpoint(): void
    {
        $endpoint = rtrim((string) config('edutrustpay.endpoint'), '/');

        if ($endpoint === '') {
            return;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get($endpoint.'/api/v1/contract');
        } catch (\Throwable $e) {
            // Not fatal: an outbox exists precisely because this site's network
            // is not always up.
            $this->warn2('Endpoint', 'Unreachable right now — reports will queue and retry. ('.$e->getMessage().')');

            return;
        }

        if (! $response->successful()) {
            $this->warn2('Endpoint', 'Responded HTTP '.$response->status());

            return;
        }

        $remote = (string) $response->json('contract_version');

        $remote === ContractVersion::CURRENT
            ? $this->pass('Endpoint', 'reachable and speaking contract v'.$remote)
            : $this->warn2('Endpoint', sprintf(
                'speaks contract v%s while this client builds v%s. Upgrade before the versions diverge further.',
                $remote,
                ContractVersion::CURRENT
            ));

        $this->checkCredentials($endpoint);
    }

    /**
     * Prove the credentials actually work.
     *
     * The check above only pings /contract, which is deliberately
     * unauthenticated — so on its own it will happily report "all checks
     * passed" while this client holds a key that was rotated or revoked weeks
     * ago. That is the single most likely real-world failure here, and it is
     * invisible until month end when the reports start bouncing.
     *
     * A heartbeat is the cheapest way to ask "does this key still work". It
     * carries no figures, is signed exactly like a report, and doubles as a
     * useful thing to have sent: it tells the console this institution is alive.
     */
    private function checkCredentials(string $endpoint): void
    {
        $body = json_encode(['note' => 'Credential check from edutrustpay:doctor.']);
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        try {
            $response = Http::withHeaders([
                'X-Edutrust-Key-Id' => (string) config('edutrustpay.key_id'),
                'X-Edutrust-Timestamp' => $timestamp,
                'X-Edutrust-Signature' => Signer::signRaw($body, $timestamp, (string) config('edutrustpay.secret')),
                'Accept' => 'application/json',
            ])->withBody($body, 'application/json')->timeout(10)->post($endpoint.'/api/v1/heartbeat');
        } catch (\Throwable $e) {
            $this->warn2('Credentials', 'Could not be checked — the endpoint is unreachable.');

            return;
        }

        if ($response->successful()) {
            $this->pass('Credentials', 'accepted by the console; a heartbeat was recorded');

            return;
        }

        if ($response->status() === 401) {
            // Unknown key and bad signature are answered identically by the
            // console, on purpose — so say what to check rather than guessing.
            $this->fail('Credentials', sprintf(
                'REJECTED (401). The key id or secret is wrong, has been rotated, or has been revoked. '
                .'Ask the operator to reissue and update EDUTRUSTPAY_KEY_ID and EDUTRUSTPAY_SECRET. '
                .'Current key id: %s',
                (string) config('edutrustpay.key_id')
            ));

            return;
        }

        $this->warn2('Credentials', 'Unexpected response HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 160));
    }

    private function pass(string $label, string $message): void
    {
        $this->line(sprintf('  <fg=green>✓</> <options=bold>%-16s</> %s', $label, $message));
    }

    private function warn2(string $label, string $message): void
    {
        $this->line(sprintf('  <fg=yellow>!</> <options=bold>%-16s</> %s', $label, $message));
    }

    private function fail(string $label, string $message): void
    {
        $this->problems++;
        $this->line(sprintf('  <fg=red>✗</> <options=bold>%-16s</> %s', $label, $message));
    }

    private function note(string $message): void
    {
        $this->line('      <fg=gray>'.$message.'</>');
    }
}
