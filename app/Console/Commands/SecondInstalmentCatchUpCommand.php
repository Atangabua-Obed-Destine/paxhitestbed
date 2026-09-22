<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Services\SecondInstalmentCatchUp;
use Illuminate\Console\Command;

/**
 * The Second Instalment catch-up, for running on production from a terminal.
 *
 *   php artisan fees:second-instalment-catchup --session=2
 *   php artisan fees:second-instalment-catchup --session=2 --apply
 *
 * Without --apply it only reports. The same service the screen uses, so the two
 * can never disagree. Take a database backup before --apply: it bills students.
 */
class SecondInstalmentCatchUpCommand extends Command
{
    protected $signature = 'fees:second-instalment-catchup
                            {--session= : Academic year (session id); defaults to the newest active one}
                            {--program= : Only this programme}
                            {--apply : Raise the fees; without this nothing is written}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Raise the Second Instalments that were never assigned, and let student credit settle them.';

    public function handle(SecondInstalmentCatchUp $catchUp): int
    {
        $money = fn ($amount) => number_format((float) $amount, 0);

        $session = $this->option('session')
            ? Session::find($this->option('session'))
            : (Session::where('status', '1')->orderBy('id', 'desc')->first() ?: Session::orderBy('id', 'desc')->first());

        if (!$session) {
            $this->error('No academic year to work on. Pass --session=<id>.');

            return self::FAILURE;
        }

        if (!$catchUp->category()) {
            $this->error('No fee category is marked as the second instalment.');

            return self::FAILURE;
        }

        $rows = $catchUp->preview($session->id, $this->option('program') ? (int) $this->option('program') : null);

        $this->line('');
        $this->info("Second Instalment catch-up — {$session->title}");

        if ($rows->isEmpty()) {
            $this->line('   Every student for this year already has a Second Instalment.');

            return self::SUCCESS;
        }

        $this->table(
            ['Matricule', 'Student', 'Programme', 'Reached sem 2', 'Fee', 'Credit', 'Left owing'],
            $rows->map(fn ($row) => [
                $row['matricule'], $row['name'], $row['program'],
                $row['reached_second_semester'] ? 'yes' : 'no',
                $row['blocked'] ? '—' : $money($row['amount'] + $row['fine']),
                $money($row['credit']),
                $row['blocked'] ? $row['blocked'] : $money($row['owing']),
            ])->all()
        );

        $billable = $rows->whereNull('blocked');

        $this->line(sprintf('   %d student(s): %s raised, %s settled by credit, %s left owing.',
            $billable->count(),
            $money($billable->sum(fn ($row) => $row['amount'] + $row['fine'])),
            $money($billable->sum('credit')),
            $money($billable->sum('owing'))));

        if ($rows->whereNotNull('blocked')->isNotEmpty()) {
            $this->warn(sprintf('   %d student(s) have no Second Instalment configured for their programme and are left out.',
                $rows->whereNotNull('blocked')->count()));
        }

        if (!$this->option('apply')) {
            $this->line('');
            $this->line('Nothing was written. Re-run with --apply to raise these fees.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm(sprintf(
            'Bill %d student(s) %s, leaving %s owed? Take a database backup first.',
            $billable->count(),
            $money($billable->sum(fn ($row) => $row['amount'] + $row['fine'])),
            $money($billable->sum('owing'))
        ))) {
            $this->line('Nothing was written.');

            return self::SUCCESS;
        }

        $result = $catchUp->apply($billable->pluck('student_id')->all(), $session->id);

        $this->info(sprintf('%d fee(s) raised, %s settled by credit, %s left owing.',
            $result['billed'], $money($result['credit_applied']), $money($result['owing'])));

        foreach ($result['failed'] as $failure) {
            $this->warn("   {$failure['student']}: {$failure['reason']}");
        }

        return self::SUCCESS;
    }
}
