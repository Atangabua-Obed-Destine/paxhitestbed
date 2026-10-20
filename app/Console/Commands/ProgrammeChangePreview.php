<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Academic\ProgrammeChange;
use Illuminate\Console\Command;

/**
 * Show what moving a student to another programme would do.
 *
 * Read-only. It writes nothing and cannot write anything - it only ever calls
 * preview(), never apply(). The point is to let somebody check the decisions on
 * real students before there is a button that acts on them.
 */
class ProgrammeChangePreview extends Command
{
    protected $signature = 'student:programme-change-preview
                            {student : Matricule or student id, e.g. PAX25AFH002}
                            {programme? : The target programme id. Omitted, it lists the choices}
                            {--enrolment= : Which enrolment to move, when the student has several}';

    protected $description = 'Preview a student programme change and its fee effect (read-only)';

    public function handle(ProgrammeChange $service): int
    {
        $student = Student::where('student_id', $this->argument('student'))->first();

        if (!$student) {
            $enrolment = StudentEnroll::where('matricule', $this->argument('student'))->latest('id')->first();
            $student = $enrolment ? Student::find($enrolment->student_id) : null;
        }

        if (!$student) {
            $this->error('No student with that matricule or id.');

            return self::FAILURE;
        }

        $enrolments = StudentEnroll::where('student_id', $student->id)->orderBy('id')->get();

        if ($enrolments->isEmpty()) {
            $this->error('That student has no enrolment to move.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info($student->first_name . ' ' . $student->last_name . '  (' . $student->student_id . ')');

        $this->table(
            ['enrolment', 'programme', 'semester', 'matricule', 'fees'],
            $enrolments->map(fn ($e) => [
                $e->id,
                mb_strimwidth((string) optional(Program::find($e->program_id))->title, 0, 34, '...'),
                optional($e->semester)->title,
                $e->matricule,
                $e->fees()->count(),
            ])->all()
        );

        if (!$this->argument('programme')) {
            $this->line('Choose a target programme and run again, e.g.:');
            $this->line('  php artisan student:programme-change-preview ' . $student->student_id . ' <programme-id>');
            $this->newLine();

            $this->table(['id', 'programme', 'fee structure', 'subject list'],
                Program::where('status', '1')->orderBy('id')->get()->map(fn ($p) => [
                    $p->id,
                    mb_strimwidth((string) $p->title, 0, 42, '...'),
                    \App\Models\ProgramSemesterFee::where('program_id', $p->id)->where('status', 1)->exists() ? 'yes' : 'NO',
                    \Illuminate\Support\Facades\DB::table('program_subject')->where('program_id', $p->id)->exists() ? 'yes' : 'NO',
                ])->all());

            return self::SUCCESS;
        }

        $targetId = (int) $this->argument('programme');

        $chosen = $this->option('enrolment')
            ? $enrolments->firstWhere('id', (int) $this->option('enrolment'))
            : $enrolments->last();

        if (!$chosen) {
            $this->error('That enrolment does not belong to this student.');

            return self::FAILURE;
        }

        $preview = $service->preview($chosen, $targetId);

        if (!$preview['to']) {
            $this->error('No such programme.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Enrolment #' . $chosen->id);
        $this->line('  from : ' . optional($preview['from'])->title);
        $this->line('  to   : ' . optional($preview['to'])->title);
        $this->line('  new matricule would be: ' . ($preview['matricule'] ?? 'could not be generated'));

        $this->newLine();

        if ($preview['blockers'] !== []) {
            $this->error('This move would be REFUSED:');

            foreach ($preview['blockers'] as $reason) {
                $this->line('  - ' . $reason);
            }
        } else {
            $this->info('This move would be allowed.');
        }

        foreach ($preview['warnings'] as $warning) {
            $this->warn('  ! ' . $warning);
        }

        $this->newLine();
        $this->line('Registered courses: ' . $preview['courses']['kept']->count() . ' kept, '
            . $preview['courses']['orphans']->count() . ' not taught by the new programme');

        foreach ($preview['courses']['orphans'] as $course) {
            $this->line('    ' . $course['code'] . ' - ' . $course['title']
                . ($course['has_marks'] ? '   (HAS MARKS)' : ''));
        }

        $this->newLine();

        if ($preview['fees']->isEmpty()) {
            $this->line('No fees to reconcile.');
        } else {
            $this->table(
                ['fee', 'category', 'now', 'would be', 'what happens'],
                $preview['fees']->map(fn ($line) => [
                    $line['fee'] ? '#' . $line['fee']->id : 'new',
                    mb_strimwidth((string) $line['category'], 0, 20, '...'),
                    number_format($line['current'], 2),
                    number_format($line['new'], 2),
                    mb_strimwidth($line['why'], 0, 62, '...'),
                ])->all()
            );
        }

        $this->newLine();
        $this->warn('Nothing has been changed. This command only previews.');

        return self::SUCCESS;
    }
}
