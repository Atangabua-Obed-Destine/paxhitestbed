<?php

namespace App\Console\Commands;

use App\Services\Resit\ResitInheritanceAudit;
use Illuminate\Console\Command;

/**
 * Carry attendance and continuous assessment into resit sittings that never
 * received it.
 *
 * A resit sitting inherits from the semester it shadows: the attendance
 * records, the non-final (CA) exam rows with the marks already earned, and the
 * subject marking. The student-initiated routes have always done this. The
 * admin-initiated route — the Resit Enrolments screen — created the enrolment
 * and registered the courses but never made the call, so every sitting raised
 * that way holds no CA mark and, worse, no exam row at all. With no exam row
 * the student is absent from the CA marking screen entirely, and the only
 * thing that brings a row into existence is saving exam attendance, which
 * continuous assessment does not otherwise require.
 *
 * ResitEnrolmentBackfill now inherits on its own, so this is only for the
 * sittings raised before it did. It is safe to run at any time and as often as
 * you like: every step of the inheritance checks before it writes, so a
 * sitting that already carries its data is left exactly as it is.
 */
class InheritResitSemesterDataCommand extends Command
{
    protected $signature = 'resits:inherit
                            {--dry-run : List what would be inherited and change nothing}
                            {--enrolment=* : Limit to these resit enrolment ids}';

    protected $description = 'Inherit attendance and CA marks into resit enrolments that are missing them';

    public function __construct(protected ResitInheritanceAudit $audit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // The same detector the Resit Enrolments screen reads, so the command
        // and the screen can never disagree about who needs repairing.
        $affected = $this->audit->affected(
            $this->option('enrolment') ?: null
        );

        if ($affected->isEmpty()) {
            $this->info('Every resit sitting already carries what it inherits. Nothing to do.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($affected as $row) {
            $sitting = $row['enrolment'];
            $before = $this->audit->countsFor($sitting->id);

            $after = $dryRun ? null : $this->repairAndCount($row);

            $rows[] = [
                $sitting->id,
                $sitting->matricule ?? optional($sitting->student)->student_id,
                collect($row['missing'])->pluck('subject_code')->implode(', '),
                $dryRun ? $before['ca'] . ' → ?' : $before['ca'] . ' → ' . $after['ca'],
                $dryRun ? $before['attendance'] . ' → ?' : $before['attendance'] . ' → ' . $after['attendance'],
                $dryRun ? $before['marking'] . ' → ?' : $before['marking'] . ' → ' . $after['marking'],
            ];
        }

        $this->table(
            ['Enrolment', 'Matricule', 'Courses missing their CA', 'CA rows', 'Attendance', 'Subject markings'],
            $rows
        );

        if ($dryRun) {
            $this->warn($affected->count() . ' resit sitting(s) are missing inherited data. Re-run without --dry-run to carry it over.');

            return self::SUCCESS;
        }

        $this->info($affected->count() . ' resit sitting(s) inherited from the semester they shadow.');

        return self::SUCCESS;
    }

    /**
     * @return array{ca: int, attendance: int, marking: int}
     */
    protected function repairAndCount(array $row): array
    {
        $this->audit->repair($row);

        return $this->audit->countsFor($row['enrolment']->id);
    }
}
