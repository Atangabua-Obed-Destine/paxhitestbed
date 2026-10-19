<?php

namespace App\Console\Commands;

use App\Models\Program;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report programmes whose semester ladder looks unfinished.
 *
 * Now that a Year has to be chosen before a Semester, a programme with only
 * one semester attached offers a Year dropdown with a single option, and a
 * student in a year that is not attached has no option at all. That reads as
 * the new filter being broken when it is really the catalogue being incomplete.
 *
 * This only reports. It writes nothing: which semesters a programme teaches is
 * an academic decision, not something a command should guess at.
 */
class SemesterLadderDoctor extends Command
{
    protected $signature = 'academic:semester-ladder-doctor
                            {--program= : Limit the report to one programme id}';

    protected $description = 'List programmes whose attached semesters look incomplete (read-only)';

    public function handle(): int
    {
        $programs = Program::query()
            ->when($this->option('program'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();

        if ($programs->isEmpty()) {
            $this->info('No programmes to inspect.');

            return self::SUCCESS;
        }

        $attached = DB::table('program_semester')
            ->join('semesters', 'semesters.id', '=', 'program_semester.semester_id')
            ->select('program_semester.program_id', 'semesters.id', 'semesters.title',
                'semesters.year', 'semesters.is_resit', 'semesters.parent_semester_id')
            ->get()
            ->groupBy('program_id');

        $enrolledYears = DB::table('student_enrolls')
            ->join('semesters', 'semesters.id', '=', 'student_enrolls.semester_id')
            ->select('student_enrolls.program_id', 'semesters.year')
            ->distinct()
            ->get()
            ->groupBy('program_id');

        $rows = [];

        foreach ($programs as $program) {
            $mine = $attached->get($program->id, collect());
            $years = $mine->pluck('year')->filter()->unique()->sort()->values();
            $problems = [];

            if ($mine->isEmpty()) {
                $problems[] = 'no semesters attached at all';
            } else {
                if ($years->count() < 2) {
                    $problems[] = 'only year ' . ($years->implode(', ') ?: 'none');
                }

                // A later year present without the one below it.
                $numeric = $years->map(fn ($y) => (int) $y)->sort()->values();

                if ($numeric->isNotEmpty() && $numeric->first() > 1) {
                    $problems[] = 'starts at year ' . $numeric->first() . ', no year 1';
                }

                foreach ($numeric as $index => $year) {
                    if ($index > 0 && $year - $numeric[$index - 1] > 1) {
                        $problems[] = 'gap between year ' . $numeric[$index - 1] . ' and ' . $year;
                    }
                }

                // A resit whose parent the programme does not teach.
                $ids = $mine->pluck('id')->all();

                foreach ($mine->where('is_resit', 1) as $resit) {
                    if ($resit->parent_semester_id && !in_array($resit->parent_semester_id, $ids, true)) {
                        $problems[] = 'resit "' . $resit->title . '" without its parent semester';
                    }
                }
            }

            // Students enrolled in a year the programme does not offer.
            $taught = $years->map(fn ($y) => (string) $y)->all();

            foreach ($enrolledYears->get($program->id, collect()) as $row) {
                if ($row->year !== null && !in_array((string) $row->year, $taught, true)) {
                    $problems[] = 'students enrolled in year ' . $row->year . ', which is not attached';
                }
            }

            if ($problems !== []) {
                $rows[] = [
                    $program->id,
                    mb_strimwidth((string) $program->title, 0, 44, '...'),
                    $mine->count(),
                    $years->implode(', ') ?: '-',
                    implode('; ', array_unique($problems)),
                ];
            }
        }

        if ($rows === []) {
            $this->info('Every programme has a complete-looking semester ladder.');

            return self::SUCCESS;
        }

        $this->table(['id', 'programme', 'semesters', 'years', 'what looks wrong'], $rows);

        $this->newLine();
        $this->warn($this->describeCount(count($rows), count($programs)));
        $this->line('Nothing has been changed. Attach the missing semesters under '
            . 'Academics > Semester, or from the programme itself.');

        return self::SUCCESS;
    }

    private function describeCount(int $flagged, int $total): string
    {
        return $flagged . ' of ' . $total . ' programme(s) look incomplete. Until they are '
            . 'filled in, their Year dropdown will offer only the years listed above.';
    }
}
