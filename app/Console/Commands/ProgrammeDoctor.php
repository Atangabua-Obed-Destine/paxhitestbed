<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Students that have gone missing, and why.
 *
 * `student_enrolls` cascades on program_id, session_id, semester_id and
 * section_id. Deleting any of those parents erases the enrolments inside MySQL,
 * where Eloquent cannot see it, so the Auditable trait records nothing. The
 * student then vanishes from the admin student list - which is built only from
 * enrolments - and their programme reads as blank, because students.program_id
 * and applications.program_id have no foreign key and are left pointing at a
 * row that is gone.
 *
 * This reports that damage and nothing else. It writes only with --apply, and
 * then only what needs no judgement: a BLANK programme filled from a record that
 * still knows it, and a blank matricule. A programme that merely differs is left
 * alone and reported, because students do transfer after admission and the
 * difference is usually real history.
 *
 * Bringing a deleted programme back is a decision about what the school still
 * teaches, so it is a separate, explicit flag.
 */
class ProgrammeDoctor extends Command
{
    protected $signature = 'admissions:programme-doctor
                            {--apply : Fill in what is missing. Without this nothing is written.}
                            {--restore-programme= : Recreate one deleted programme from the audit log, by its old id.}';

    protected $description = 'Find students whose enrolments or programmes were lost, and optionally repair what is safe to repair.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $restore = $this->option('restore-programme');

        $this->info('Programme doctor — ' . now()->format('d M Y H:i'));

        if ($restore) {
            return $this->restoreProgramme((int) $restore, $apply);
        }

        $this->line($apply
            ? '  Running with --apply: blanks will be filled.'
            : '  Read-only. Nothing will be written. Add --apply to repair.');

        $danglingStudents = $this->danglingStudents();
        $invisible = $this->invisibleStudents();
        $applications = $this->applicationsMissingProgramme();
        $blankMatricules = $this->blankMatricules();
        $missingProgrammes = $this->missingProgrammeIds();

        $this->reportDangling($danglingStudents);
        $this->reportInvisible($invisible);
        $this->reportApplications($applications);
        $this->reportMatricules($blankMatricules);
        $this->reportMissingProgrammes($missingProgrammes);

        if (!$apply) {
            $this->newLine();
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Applying the repairs that need no judgement');

        $filled = $this->fillApplicationProgrammes($applications);
        $named = $this->fillMatricules($blankMatricules);

        $this->line("  application programmes filled from the student's own record: {$filled}");
        $this->line("  blank matricules filled in                                 : {$named}");

        if ($danglingStudents->isNotEmpty() || $invisible->isNotEmpty()) {
            $this->newLine();
            $this->warn('  Students with a deleted programme or no enrolment were NOT repaired.');
            $this->line('  Rebuilding an enrolment needs a programme to attach it to. Decide whether');
            $this->line('  the deleted programme should come back, then run:');
            $this->line('     php artisan admissions:programme-doctor --restore-programme=<id> --apply');
        }

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // Finding
    // -----------------------------------------------------------------------

    /** Students pointing at a programme that no longer exists. */
    protected function danglingStudents()
    {
        return Student::query()
            ->whereNotNull('program_id')
            ->whereNotIn('program_id', Program::query()->select('id'))
            ->get();
    }

    /** Active students with no enrolment at all — invisible on the student list. */
    protected function invisibleStudents()
    {
        return Student::query()
            ->where('status', '1')
            ->whereDoesntHave('studentEnrolls')
            ->get();
    }

    /**
     * Applications whose programme is blank or dangling, where the student the
     * application became still knows the answer.
     */
    protected function applicationsMissingProgramme()
    {
        $live = Program::query()->select('id');

        return Application::query()
            ->whereNotNull('registration_no')
            ->where(function ($query) use ($live) {
                $query->whereNull('program_id')->orWhereNotIn('program_id', $live);
            })
            ->get()
            ->map(function (Application $application) {
                $student = Student::where('registration_no', $application->registration_no)->first();

                if (!$student) {
                    return null;
                }

                // The enrolment is the better source: it is what the student was
                // actually put onto. The student record is the fallback.
                $enrolled = StudentEnroll::where('student_id', $student->id)
                    ->whereIn('program_id', Program::query()->select('id'))
                    ->orderBy('id')
                    ->value('program_id');

                $source = $enrolled ?: $student->program_id;

                if (!$source || !Program::whereKey($source)->exists()) {
                    return null;
                }

                return [
                    'application' => $application,
                    'student' => $student,
                    'programme' => Program::find($source),
                    'from' => $enrolled ? 'enrolment' : 'student record',
                ];
            })
            ->filter()
            ->values();
    }

    protected function blankMatricules()
    {
        return StudentEnroll::query()
            ->where(function ($query) {
                $query->whereNull('matricule')->orWhere('matricule', '');
            })
            ->with('student')
            ->get();
    }

    /** Programme ids still referenced by students or applications but deleted. */
    protected function missingProgrammeIds(): array
    {
        $live = Program::pluck('id')->all();

        $referenced = collect()
            ->merge(Student::whereNotNull('program_id')->pluck('program_id'))
            ->merge(Application::whereNotNull('program_id')->pluck('program_id'))
            ->merge(StudentEnroll::pluck('program_id'))
            ->unique()
            ->values();

        return $referenced->reject(fn ($id) => in_array($id, $live))->values()->all();
    }

    // -----------------------------------------------------------------------
    // Reporting
    // -----------------------------------------------------------------------

    protected function reportDangling($students): void
    {
        $this->newLine();
        $this->line('1. Students whose programme no longer exists');

        if ($students->isEmpty()) {
            $this->line('   none');

            return;
        }

        $this->table(
            ['Student', 'Matricule', 'Points at programme', 'Enrolments left'],
            $students->map(fn (Student $s) => [
                trim($s->first_name . ' ' . $s->last_name),
                $s->student_id,
                $s->program_id,
                StudentEnroll::where('student_id', $s->id)->count(),
            ])->all()
        );
    }

    protected function reportInvisible($students): void
    {
        $this->newLine();
        $this->line('2. Active students with no enrolment — these do not appear on the student list');

        if ($students->isEmpty()) {
            $this->line('   none');

            return;
        }

        $this->table(
            ['Student', 'Matricule', 'Programme on the student record'],
            $students->map(fn (Student $s) => [
                trim($s->first_name . ' ' . $s->last_name),
                $s->student_id,
                optional(Program::find($s->program_id))->title ?? ('#' . $s->program_id . ' (gone)'),
            ])->all()
        );
    }

    protected function reportApplications($rows): void
    {
        $this->newLine();
        $this->line('3. Applications with no programme, where the student still knows it');

        if ($rows->isEmpty()) {
            $this->line('   none');

            return;
        }

        $this->table(
            ['Application', 'Registration', 'Would be filled with', 'Taken from'],
            $rows->map(fn ($row) => [
                '#' . $row['application']->id,
                $row['application']->registration_no,
                $row['programme']->title,
                $row['from'],
            ])->all()
        );
    }

    protected function reportMatricules($enrolments): void
    {
        $this->newLine();
        $this->line('4. Enrolments with no matricule — the student list collapses these into one row');

        if ($enrolments->isEmpty()) {
            $this->line('   none');

            return;
        }

        $active = $enrolments->where('status', '1');
        $this->line('   ' . $enrolments->count() . ' enrolment(s), of which '
            . $active->count() . ' are active and so affect the default view.');
    }

    protected function reportMissingProgrammes(array $ids): void
    {
        $this->newLine();
        $this->line('5. Programmes that were deleted but are still referenced');

        if ($ids === []) {
            $this->line('   none');

            return;
        }

        $rows = [];

        foreach ($ids as $id) {
            $audit = DB::table('audit_logs')
                ->where('auditable_type', 'App\\Models\\Program')
                ->where('auditable_id', $id)
                ->where('event', 'deleted')
                ->orderByDesc('id')
                ->first();

            $old = $audit ? $this->decode($audit->old_values) : [];

            $rows[] = [
                $id,
                $old['title'] ?? '(not in the audit log)',
                $audit->created_at ?? '-',
                $audit->user_id ?? '-',
            ];
        }

        $this->table(['Programme id', 'Title when deleted', 'Deleted at', 'By user'], $rows);
        $this->line('   To bring one back and rebuild its enrolments:');
        $this->line('     php artisan admissions:programme-doctor --restore-programme=<id> --apply');
    }

    // -----------------------------------------------------------------------
    // Repairing
    // -----------------------------------------------------------------------

    protected function fillApplicationProgrammes($rows): int
    {
        $filled = 0;

        foreach ($rows as $row) {
            $application = $row['application'];
            $application->program_id = $row['programme']->id;
            $application->save();
            $filled++;
        }

        return $filled;
    }

    protected function fillMatricules($enrolments): int
    {
        $named = 0;

        foreach ($enrolments as $enroll) {
            $matricule = optional($enroll->student)->student_id;

            if (!$matricule) {
                continue;
            }

            $enroll->matricule = $matricule;
            $enroll->save();
            $named++;
        }

        return $named;
    }

    /**
     * Recreate one deleted programme from what the audit log remembers, so its
     * students can be enrolled again.
     */
    protected function restoreProgramme(int $id, bool $apply): int
    {
        if (Program::whereKey($id)->exists()) {
            $this->error("Programme #{$id} already exists. Nothing to restore.");

            return self::FAILURE;
        }

        $audit = DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\Program')
            ->where('auditable_id', $id)
            ->where('event', 'deleted')
            ->orderByDesc('id')
            ->first();

        if (!$audit) {
            $this->error("No audit record of programme #{$id} being deleted, so there is nothing to restore it from.");

            return self::FAILURE;
        }

        $old = $this->decode($audit->old_values);

        $this->line('  Programme #' . $id . ': ' . ($old['title'] ?? '?'));
        $this->line('  Deleted ' . $audit->created_at . ' by user ' . ($audit->user_id ?? '-'));

        $students = Student::where('program_id', $id)->get();
        $this->line('  ' . $students->count() . ' student(s) still name it.');

        if (!$apply) {
            $this->newLine();
            $this->line('Nothing was changed. Add --apply to restore it.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($id, $old) {
            $programme = new Program;
            $programme->id = $id;

            foreach ($old as $column => $value) {
                if (in_array($column, ['id', 'created_at', 'updated_at'], true)) {
                    continue;
                }

                $programme->{$column} = $value;
            }

            $programme->save();
        });

        $this->info("  Programme #{$id} restored.");
        $this->line('  Their enrolments were erased by the cascade and cannot be recovered from the');
        $this->line('  audit log, because MySQL deleted them without Eloquent ever seeing them.');
        $this->line('  Re-enrol those students through Student → Single Enroll.');

        return self::SUCCESS;
    }

    /** Audit values are stored as JSON, sometimes double-encoded. */
    protected function decode($value): array
    {
        $decoded = json_decode((string) $value, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
