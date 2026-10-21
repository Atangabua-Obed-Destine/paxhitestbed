<?php

namespace App\Services\Chat;

use Illuminate\Support\Str;

/**
 * The screens the assistant can take a member of staff to.
 *
 * The point of this is the question nobody can answer from the menu: "I want
 * to submit marks for my course" — where is that? A system this size has over
 * five hundred admin pages, and the person who needs one of them usually
 * knows the task, not the menu path.
 *
 * WHY THIS IS A CURATED LIST rather than every route. Permissions cannot be
 * read off a route: Route::gatherMiddleware() ignores the ->only() scoping a
 * controller constructor uses, so the student list appears to require
 * student-import when it does not. Deriving automatically would hide pages
 * people can actually open. Each entry below therefore names the permission
 * that genuinely gates that screen, and the suite proves it by loading the
 * page as somebody who holds it and somebody who does not.
 *
 * Nothing here grants anything. The screen itself still enforces its own
 * permission; this only decides what to offer, so the worst a mistake can do
 * is point somebody at a page that then refuses them.
 */
class ScreenDirectory
{
    /**
     * Each entry: the route to open, the permissions that let somebody open
     * it (holding ANY one is enough, matching Laravel's "a|b|c" middleware),
     * and the words a person would actually use for the task.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function screens(): array
    {
        return [
            // ---- teaching -------------------------------------------------
            [
                'route' => 'admin.exam-marking.index',
                'label' => 'Submit exam marks',
                'description' => 'Enter and save marks for the courses you teach.',
                'permissions' => ['exam-marking'],
                'keywords' => 'submit marks enter marks record marks exam marks marking grade grades
                               score scores upload marks my assigned courses marksheet',
            ],
            [
                'route' => 'admin.subject-marking.index',
                'label' => 'Subject marking',
                'description' => 'Continuous assessment and subject marks.',
                'permissions' => ['subject-marking'],
                'keywords' => 'subject marking continuous assessment ca marks component marks',
            ],
            [
                'route' => 'admin.exam-attendance.index',
                'label' => 'Exam attendance',
                'description' => 'Record who sat an examination.',
                'permissions' => ['exam-attendance'],
                'keywords' => 'exam attendance examination attendance who sat the exam exam register',
            ],
            [
                'route' => 'admin.student-attendance.index',
                'label' => 'Class attendance',
                'description' => 'Take the register for a class.',
                'permissions' => ['student-attendance-action'],
                'keywords' => 'attendance take attendance class attendance register mark present
                               absent daily attendance student attendance submit attendance',
            ],
            [
                'route' => 'admin.student-attendance.scanner',
                'label' => 'Attendance scanner',
                'description' => 'Scan student cards to mark attendance.',
                'permissions' => ['student-attendance-action'],
                'keywords' => 'scan scanner qr code card scan attendance check in',
            ],
            [
                'route' => 'admin.class-session.index',
                'label' => 'Class sessions',
                'description' => 'Start, run and close a class session.',
                'permissions' => ['class-session-view'],
                'keywords' => 'class session start a class begin lecture run a session
                               end session close session lesson',
            ],
            [
                'route' => 'admin.class-session.kiosk',
                'label' => 'Class session kiosk',
                'description' => 'The kiosk screen students check in on.',
                'permissions' => ['class-session-create'],
                'keywords' => 'kiosk check in students sign in to class session display',
            ],

            // ---- timetables -----------------------------------------------
            [
                'route' => 'admin.class-routine.index',
                'label' => 'Class timetable',
                'description' => 'The weekly timetable for one class group.',
                'permissions' => ['class-routine-view', 'class-routine-create', 'class-routine-print'],
                'keywords' => 'timetable time table class routine schedule when do i teach
                               lecture schedule weekly schedule',
            ],
            [
                'route' => 'admin.class-routine.overview',
                'label' => 'Timetable overview',
                'description' => 'Every timetable in the session, and what is clashing.',
                'permissions' => ['class-routine-view', 'class-routine-create', 'class-routine-print'],
                'keywords' => 'all timetables timetable overview whole session clashes conflicts
                               double booked unscheduled programmes',
            ],
            [
                'route' => 'admin.exam-routine.index',
                'label' => 'Exam timetable',
                'description' => 'The examination schedule.',
                'permissions' => ['exam-routine-view', 'exam-routine-create', 'exam-routine-print'],
                'keywords' => 'exam timetable exam schedule examination routine when is the exam',
            ],

            // ---- results --------------------------------------------------
            [
                'route' => 'admin.exam-publishing.index',
                'label' => 'Publish results',
                'description' => 'Release results to students.',
                'permissions' => ['exam-publishing-view'],
                'keywords' => 'publish results release results make results available
                               publishing results approval',
            ],
            [
                'route' => 'admin.results-summary.index',
                'label' => 'Results summary',
                'description' => 'Marksheets and result reports.',
                'permissions' => ['results-summary-view'],
                'keywords' => 'results summary marksheet course marksheet faculty report
                               department report student results',
            ],

            // ---- students and admissions ----------------------------------
            [
                'route' => 'admin.single-enroll.index',
                'label' => 'Enrol a student',
                'description' => 'Enrol a student, or change their programme.',
                'permissions' => ['student-enroll-single'],
                'keywords' => 'enrol enroll enrolment enrollment register a student
                               change programme programme change matricule',
            ],
            [
                'route' => 'admin.student.index',
                'label' => 'Students',
                'description' => 'The student register.',
                'permissions' => ['student-view', 'student-create', 'student-edit', 'student-delete', 'student-card'],
                'keywords' => 'students student list student record find a student student register',
            ],
            [
                'route' => 'admin.application.index',
                'label' => 'Applications',
                'description' => 'Admission applications and their stages.',
                'permissions' => ['application-view', 'application-create', 'application-edit', 'application-delete'],
                'keywords' => 'applications admission applicants apply admissions review applications',
            ],

            // ---- money ----------------------------------------------------
            [
                'route' => 'admin.income.index',
                'label' => 'Income',
                'description' => 'Record and review income.',
                'permissions' => ['income-view', 'income-create', 'income-edit', 'income-delete'],
                'keywords' => 'income revenue money received record income',
            ],
            [
                'route' => 'admin.expense.index',
                'label' => 'Expenses',
                'description' => 'Record and review expenses.',
                'permissions' => ['expense-view', 'expense-create', 'expense-edit', 'expense-delete'],
                'keywords' => 'expense expenses spending payment out record an expense',
            ],
            [
                'route' => 'admin.budget-line.index',
                'label' => 'Budget lines',
                'description' => 'The Income and Expenditure sheet and its lines.',
                'permissions' => ['budget-line-view'],
                'keywords' => 'budget line budget lines income and expenditure sheet
                               chart of accounts mapping categories',
            ],
        ];
    }

    /**
     * Screens matching a described task, best first.
     *
     * Returns every match with a flag saying whether this person may open it,
     * so the caller can offer the permitted ones and be honest about the rest
     * instead of pretending the screen does not exist.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $task, ChatContext $context, int $limit = 4): array
    {
        $terms = $this->terms($task);

        if ($terms === []) {
            return [];
        }

        $scored = [];

        foreach (static::screens() as $screen) {
            $haystack = ' ' . $this->normalise(
                $screen['label'] . ' ' . $screen['description'] . ' ' . $screen['keywords']
            ) . ' ';

            $score = 0;
            $wholeWordHits = 0;

            foreach ($terms as $term) {
                // A whole-word hit is worth much more than a fragment, so
                // "marks" does not quietly match "remarks".
                if (str_contains($haystack, ' ' . $term . ' ')) {
                    $score += 10;
                    $wholeWordHits++;
                } elseif (mb_strlen($term) >= 5 && str_contains($haystack, $term)) {
                    $score += 3;
                }
            }

            // A phrase the person actually typed, appearing intact, is the
            // strongest signal there is.
            $phrase = $this->normalise($task);

            if (mb_strlen($phrase) >= 6 && str_contains($haystack, $phrase)) {
                $score += 25;
            }

            // One shared word is not a match. "Order new chairs for the
            // lecture hall" hit "lecture" and was offered Class sessions,
            // which is worse than saying nothing: a confident wrong screen
            // sends somebody down the wrong path. A longer question has to
            // agree on more than one word, or on a phrase outright.
            $enough = $wholeWordHits >= 2
                || $score >= 25
                || ($wholeWordHits >= 1 && count($terms) <= 2);

            if ($score <= 0 || !$enough) {
                continue;
            }

            $screen['score'] = $score;
            $screen['permitted'] = $this->permits($screen, $context);
            $screen['url'] = route($screen['route']);
            $scored[] = $screen;
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /** May this person open the screen? Any one of its permissions is enough. */
    public function permits(array $screen, ChatContext $context): bool
    {
        foreach ($screen['permissions'] as $permission) {
            if ($context->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /** Words worth matching on, with the filler dropped. */
    protected function terms(string $task): array
    {
        $stop = ['i', 'want', 'to', 'the', 'a', 'an', 'my', 'me', 'for', 'of', 'on', 'in', 'at',
            'how', 'do', 'can', 'where', 'is', 'it', 'and', 'with', 'please', 'help', 'open',
            'go', 'take', 'show', 'find', 'need', 'would', 'like', 'you', 'this', 'that', 'some'];

        $words = preg_split('/\s+/', $this->normalise($task), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn ($word) => mb_strlen($word) > 1 && !in_array($word, $stop, true)
        ));
    }

    /** Lower case, no punctuation, single spaces. */
    protected function normalise(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ',
            preg_replace('/[^a-z0-9 ]+/', ' ', Str::lower($value))));
    }
}
