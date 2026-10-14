<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use Illuminate\Database\Eloquent\Model;

/**
 * What still depends on a programme, session, semester or section.
 *
 * `student_enrolls` carries ON DELETE CASCADE on program_id, session_id,
 * semester_id, section_id and student_id. Deleting any one of those parents
 * therefore destroys every enrolment hanging off it - inside MySQL, so Eloquent
 * never loads the rows and the Auditable trait records nothing at all. The
 * enrolments are simply gone, with no trace of what was lost.
 *
 * The student then disappears from the admin student list, because that list is
 * built only from enrolments, and their programme reads as blank, because
 * students.program_id and applications.program_id have no foreign key and are
 * left pointing at a row that no longer exists.
 *
 * That has already happened here: three programmes have been deleted on this
 * database, two of them seconds apart.
 *
 * So nothing may be deleted out from under a student. One class, used by all
 * four screens, because a rule enforced on the programme screen and forgotten on
 * the session screen is not a rule.
 */
class AcademicDependencyGuard
{
    /**
     * What depends on this record, counted by kind.
     *
     * @return array<string, int>
     */
    public function dependents(Model $record): array
    {
        $column = $this->columnFor($record);

        if (!$column) {
            return [];
        }

        $counts = [
            'enrolments' => StudentEnroll::where($column, $record->getKey())->count(),
            'students' => StudentEnroll::where($column, $record->getKey())
                ->distinct()->count('student_id'),
        ];

        // Only a programme is pointed at from students and applications, and
        // those two have no foreign key - they would be left dangling rather
        // than cascaded.
        if ($record instanceof Program) {
            $counts['student_records'] = Student::where('program_id', $record->getKey())->count();
            $counts['applications'] = Application::where('program_id', $record->getKey())->count();
        }

        return $counts;
    }

    /**
     * Why this record may not be deleted, in words a registrar can act on.
     *
     * An empty array means it can go. Each reason names a number, because
     * "cannot delete" on its own sends someone looking in the wrong place.
     *
     * @return array<int, string>
     */
    public function blockers(Model $record): array
    {
        $counts = $this->dependents($record);

        if ($counts === []) {
            return [];
        }

        $reasons = [];
        $noun = $this->nounFor($record);

        if (($counts['enrolments'] ?? 0) > 0) {
            $reasons[] = __(':students student(s) are enrolled on this :noun, across :enrolments enrolment(s). Deleting it would erase those enrolments and the students would disappear from the student list. Move them first.', [
                'students' => $counts['students'],
                'noun' => $noun,
                'enrolments' => $counts['enrolments'],
            ]);
        }

        if (($counts['student_records'] ?? 0) > 0) {
            $reasons[] = __(':count student record(s) still name this :noun.', [
                'count' => $counts['student_records'],
                'noun' => $noun,
            ]);
        }

        if (($counts['applications'] ?? 0) > 0) {
            $reasons[] = __(':count application(s) still name this :noun.', [
                'count' => $counts['applications'],
                'noun' => $noun,
            ]);
        }

        return $reasons;
    }

    public function canDelete(Model $record): bool
    {
        return $this->blockers($record) === [];
    }

    /**
     * The enrolment column that cascades from this kind of record.
     */
    protected function columnFor(Model $record): ?string
    {
        if ($record instanceof Program) {
            return 'program_id';
        }

        if ($record instanceof Session) {
            return 'session_id';
        }

        if ($record instanceof Semester) {
            return 'semester_id';
        }

        if ($record instanceof Section) {
            return 'section_id';
        }

        return null;
    }

    protected function nounFor(Model $record): string
    {
        if ($record instanceof Program) {
            return __('programme');
        }

        if ($record instanceof Session) {
            return __('session');
        }

        if ($record instanceof Semester) {
            return __('semester');
        }

        if ($record instanceof Section) {
            return __('section');
        }

        return __('record');
    }
}
