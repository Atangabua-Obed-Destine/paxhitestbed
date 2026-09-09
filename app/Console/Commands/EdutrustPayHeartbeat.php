<?php

namespace App\Console\Commands;

use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\OutboxService;
use Illuminate\Console\Command;

/**
 * Says "still here" when there is nothing to report.
 *
 * Without this, a quiet month and a dead bursary server look identical from the
 * console — and only one of them needs somebody to drive out and look. The
 * heartbeat is what makes silence mean something.
 *
 * It reports how many items are stuck in the outbox, so the body can tell the
 * difference between an institution that has stopped reporting and one that is
 * trying and failing.
 */
class EdutrustPayHeartbeat extends Command
{
    protected $signature = 'edutrustpay:heartbeat {--send : Deliver immediately rather than queueing}';

    protected $description = 'Tell EdutrustPay this institution is alive.';

    public function handle(OutboxService $outbox): int
    {
        if (! config('edutrustpay.enabled')) {
            $this->comment('EdutrustPay reporting is disabled; nothing to do.');

            return self::SUCCESS;
        }

        $pending = EdutrustPayOutbox::where('status', EdutrustPayOutbox::PENDING)->count();
        $failed = EdutrustPayOutbox::where('status', EdutrustPayOutbox::FAILED)->count();

        $item = $outbox->enqueue([
            'note' => $failed > 0
                ? 'Alive, but reports are failing to deliver from this end.'
                : 'Alive.',
            'pending_reports' => $pending,
            'failed_reports' => $failed,
            'client_version' => \EdutrustPay\Contract\ContractVersion::CURRENT,
            // A heartbeat is not a period report and carries no figures; the
            // outbox keys on this to keep it out of the report sequence.
            'period' => null,
            'sequence' => 1,
        ], 'heartbeat');

        if ($this->option('send')) {
            $outcome = $outbox->deliver($item);
            $this->line("Heartbeat $outcome.");
        } else {
            $this->line('Heartbeat queued.');
        }

        return self::SUCCESS;
    }
}
