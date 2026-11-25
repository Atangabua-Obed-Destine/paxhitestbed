<?php

namespace App\Services\Mailbox;

use App\Models\ClassRoutine;
use App\Models\Mailbox\Office;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Services\Academic\CourseGroups;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may write to whom, and who a group is.
 *
 *  - Staff may start a conversation with any active member of staff or student.
 *  - A student may start one only with their own lecturers (the teachers of
 *    their courses this session, courses in the same course group included)
 *    and with the offices set up under the mailbox settings. Anyone already in
 *    a conversation may reply in it.
 *  - Group sends are staff only. A lecturer may always write to the classes
 *    they teach; every other group needs the mailbox-broadcast permission.
 */
class Directory
{
    /** The session the school is running now. */
    public static function currentSession(): ?Session
    {
        return Session::where('status', '1')->where('current', '1')->first();
    }

    /** May this member start a new conversation with that one? */
    public function canStart(array $from, array $to): bool
    {
        if (Members::same($from, $to)) {
            return false;
        }
        $members = app(Members::class);
        if (!$members->active($to)) {
            return false;
        }
        if ($from[0] === Members::STAFF) {
            return true;
        }
        // Students write to staff only, and only to their lecturers.
        return $to[0] === Members::STAFF && in_array((int) $to[1], $this->lecturerIdsFor((int) $from[1]), true);
    }

    /** The current enrolment of a student. */
    public function enrolmentOf(int $studentId): ?StudentEnroll
    {
        return StudentEnroll::where('student_id', $studentId)->where('status', '1')->orderByDesc('id')->first();
    }

    /**
     * A student's lecturers this session: whoever teaches, on the timetable,
     * one of the courses the student is registered for (or a course grouped
     * with it).
     *
     * @return array<int, int> user ids
     */
    public function lecturerIdsFor(int $studentId): array
    {
        return array_keys($this->lecturersFor($studentId));
    }

    /**
     * @return array<int, array<int, string>> user id => course codes they teach this student
     */
    public function lecturersFor(int $studentId): array
    {
        $enrol = $this->enrolmentOf($studentId);
        if (!$enrol) {
            return [];
        }
        $courses = DB::table('student_enroll_subject')->where('student_enroll_id', $enrol->id)->pluck('subject_id')->map(fn ($v) => (int) $v)->all();
        if (!$courses) {
            return [];
        }
        $all = [];
        foreach ($courses as $c) {
            foreach (CourseGroups::memberIds($c) ?: [$c] as $m) {
                $all[(int) $m] = (int) $c;
            }
        }
        $rows = ClassRoutine::where('session_id', $enrol->session_id)->where('status', '1')
            ->whereIn('subject_id', array_keys($all))->whereNotNull('teacher_id')
            ->get(['teacher_id', 'subject_id']);
        $codes = Subject::whereIn('id', array_values($all))->pluck('code', 'id');
        $out = [];
        foreach ($rows as $r) {
            $code = $codes[$all[(int) $r->subject_id]] ?? null;
            $out[(int) $r->teacher_id][$code] = $code;
        }
        $active = User::whereIn('id', array_keys($out))->where('status', '1')->pluck('id')->map(fn ($v) => (int) $v)->all();

        return array_map('array_values', array_intersect_key($out, array_flip($active)));
    }

    /** Offices students can write to, with at least one active member. */
    public function offices(): Collection
    {
        return Office::where('status', true)
            ->whereHas('members', fn ($q) => $q->where('status', '1'))
            ->with(['members' => fn ($q) => $q->where('status', '1')])
            ->orderBy('title')->get();
    }

    /**
     * The course classes a member of staff teaches this session.
     *
     * @return Collection<int, object{id:int, code:string, title:string}>
     */
    public function coursesTaughtBy(int $userId): Collection
    {
        $session = self::currentSession();
        if (!$session) {
            return collect();
        }
        $ids = ClassRoutine::where('session_id', $session->id)->where('status', '1')->where('teacher_id', $userId)
            ->distinct()->pluck('subject_id')->all();

        return Subject::whereIn('id', $ids)->orderBy('code')->get(['id', 'code', 'title']);
    }

    /**
     * Resolve a group to its members and a label for the sender's records.
     *
     * $spec: ['type' => 'course', 'subject' => id]
     *        ['type' => 'programme', 'program' => id, 'session' => id, 'semester' => id|0, 'section' => id|0]
     *        ['type' => 'department', 'department' => id]
     *        ['type' => 'all_staff']
     *
     * @return array{members: array<int, array{0:string,1:int}>, label: string}
     */
    public function audience(array $spec): array
    {
        $type = $spec['type'] ?? '';

        if ($type === 'course') {
            $session = self::currentSession();
            $subject = Subject::find((int) ($spec['subject'] ?? 0));
            if (!$session || !$subject) {
                return ['members' => [], 'label' => ''];
            }
            $ids = CourseGroups::memberIds($subject->id) ?: [$subject->id];
            $students = DB::table('student_enroll_subject as ses')
                ->join('student_enrolls as e', 'e.id', '=', 'ses.student_enroll_id')
                ->join('students as s', 's.id', '=', 'e.student_id')
                ->whereIn('ses.subject_id', $ids)->where('e.session_id', $session->id)
                ->where('e.status', '1')->where('s.status', '1')
                ->distinct()->pluck('s.id');
            $codes = Subject::whereIn('id', $ids)->orderBy('code')->pluck('code')->implode(', ');

            return ['members' => $this->pairs(Members::STUDENT, $students), 'label' => __('Course class') . ' ' . $codes . ' (' . $session->title . ')'];
        }

        if ($type === 'programme') {
            $q = StudentEnroll::query()->where('status', '1')
                ->whereHas('student', fn ($s) => $s->where('status', '1'));
            $parts = [];
            foreach (['program' => 'program_id', 'session' => 'session_id', 'semester' => 'semester_id', 'section' => 'section_id'] as $key => $col) {
                if (!empty($spec[$key])) {
                    $q->where($col, (int) $spec[$key]);
                }
            }
            if (empty($spec['program']) || empty($spec['session'])) {
                return ['members' => [], 'label' => ''];
            }
            // A student enrolled twice in the session counts once.
            $students = $q->distinct()->pluck('student_id');
            $program = \App\Models\Program::find((int) $spec['program']);
            $parts[] = optional($program)->title;
            $parts[] = optional(\App\Models\Session::find((int) $spec['session']))->title;
            if (!empty($spec['semester'])) {
                $parts[] = optional(\App\Models\Semester::find((int) $spec['semester']))->title;
            }
            if (!empty($spec['section'])) {
                $parts[] = __('Section') . ' ' . optional(\App\Models\Section::find((int) $spec['section']))->title;
            }

            return ['members' => $this->pairs(Members::STUDENT, $students), 'label' => implode(' · ', array_filter($parts))];
        }

        if ($type === 'department') {
            $dept = \App\Models\Department::find((int) ($spec['department'] ?? 0));
            if (!$dept) {
                return ['members' => [], 'label' => ''];
            }

            return ['members' => $this->pairs(Members::STAFF, User::where('status', '1')->where('department_id', $dept->id)->pluck('id')),
                'label' => __('Department') . ': ' . $dept->title];
        }

        if ($type === 'all_staff') {
            return ['members' => $this->pairs(Members::STAFF, User::where('status', '1')->pluck('id')), 'label' => __('All staff')];
        }

        return ['members' => [], 'label' => ''];
    }

    /** May this member of staff send to this group? */
    public function canSendToGroup(User $user, array $spec): bool
    {
        if ($user->can('mailbox-broadcast')) {
            return true;
        }

        return ($spec['type'] ?? '') === 'course'
            && $this->coursesTaughtBy((int) $user->id)->contains(fn ($c) => CourseGroups::same((int) $c->id, (int) ($spec['subject'] ?? 0)));
    }

    /** Search people a member of staff may write to (for the To box). */
    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $staff = User::where('status', '1')
            ->where(fn ($q) => $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->orWhere('staff_id', 'like', $like)->orWhere('email', 'like', $like))
            ->with('designation:id,title')->orderBy('first_name')->limit($limit)->get(['id', 'staff_id', 'first_name', 'last_name', 'designation_id']);
        $students = Student::where('status', '1')
            ->where(fn ($q) => $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->orWhere('student_id', 'like', $like))
            ->orderBy('first_name')->limit($limit)->get(['id', 'student_id', 'first_name', 'last_name']);

        $out = [];
        foreach ($staff as $u) {
            $out[] = ['id' => Members::STAFF . ':' . $u->id, 'text' => trim($u->first_name . ' ' . $u->last_name) . ' — ' . (optional($u->designation)->title ?: __('Staff'))];
        }
        foreach ($students as $s) {
            $out[] = ['id' => Members::STUDENT . ':' . $s->id, 'text' => trim($s->first_name . ' ' . $s->last_name) . ' — ' . __('Student') . ' ' . $s->student_id];
        }

        return $out;
    }

    /** "staff:12" → ['staff', 12]; anything malformed → null. */
    public static function parse(string $key): ?array
    {
        if (!preg_match('/^(staff|student):(\d+)$/', $key, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    protected function pairs(string $type, $ids): array
    {
        return collect($ids)->map(fn ($id) => [$type, (int) $id])->unique(fn ($m) => $m[1])->values()->all();
    }
}
