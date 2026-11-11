<?php

namespace App\Services\EdutrustPay;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who the institute has for a month: its students, programme by programme, and
 * its staff, by name, department and post. Never pay.
 *
 * HOW A STUDENT IS COUNTED
 *
 * A student counts once, in the session the month falls in (the latest one
 * started by month end, the same choice the fees charged use), under the
 * programme of their latest enrolment in that session. student_enrolls has no
 * enrolment date, only when the row was typed in, so membership of the session
 * is what decides. Left out: rows closed by a re-enrolment or a transfer
 * (status 0), the admission-fee placeholders that name no student, and
 * students transferred out.
 *
 * The programme title is the "class", the same name the fees charged use, so
 * the console can set the two side by side.
 */
class PeopleSummaryService
{
    private string $end;

    public function __construct(string $period)
    {
        $this->end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString();
    }

    public function session(): ?object
    {
        return DB::table('sessions')
            ->whereDate('start_date', '<=', $this->end)
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * @return array<string, int>  programme title => students
     */
    public function studentsByProgramme(): array
    {
        return collect($this->studentProgrammes())
            ->countBy()
            ->sortKeys(SORT_NATURAL)
            ->all();
    }

    /**
     * The students on the roll for the session, each once, with the programme
     * of their latest enrolment in it. The fee part uses the same list to find
     * students who were never charged.
     *
     * @return array<int, string>  student id => programme title
     */
    public function studentProgrammes(): array
    {
        $session = $this->session();

        if (! $session) {
            return [];
        }

        return DB::table('student_enrolls as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
            ->where('e.session_id', $session->id)
            ->whereNotNull('e.student_id')
            ->whereIn('e.status', [1, 2])
            ->where('s.status', '!=', 3)
            ->orderBy('e.id')
            ->get(['e.student_id', 'p.title as programme'])
            ->keyBy('student_id')
            ->map(fn ($row) => $row->programme ?: 'No programme')
            ->all();
    }

    /**
     * Staff on the books at month end: active accounts that had joined by then.
     *
     * ending_date is left aside: here it sets how long an ID card stays valid,
     * not when someone left, and several people still working have one in the
     * past. Accounts whose staff number starts with a prefix in
     * edutrustpay.staff_excluded_prefixes belong to the software supplier's
     * support people, not to the institute, and are not its staff.
     *
     * @return list<array{number: ?string, name: string, department: ?string, designation: ?string}>
     */
    public function staff(): array
    {
        $excluded = (array) config('edutrustpay.staff_excluded_prefixes', []);

        return User::query()
            ->where('status', 1)
            ->where(fn ($q) => $q->whereNull('joining_date')->orWhereDate('joining_date', '<=', $this->end))
            ->with(['department', 'designation'])
            ->get()
            ->reject(fn (User $user) => collect($excluded)->contains(fn ($prefix) => $prefix !== '' && str_starts_with((string) $user->staff_id, $prefix)))
            ->map(fn (User $user) => [
                'number' => $user->staff_id ?: null,
                'name' => trim($user->first_name.' '.$user->last_name),
                'department' => $user->department?->title,
                'designation' => $user->designation?->title,
            ])
            ->filter(fn (array $person) => $person['name'] !== '')
            ->values()
            ->all();
    }
}
