<?php

namespace App\Support;

use App\Models\Field;
use Illuminate\Support\Facades\Request;

/**
 * Everywhere a student can go, in one list.
 *
 * The portal has two navigations: the sidebar a laptop shows, and the tab bar
 * and More sheet a phone shows. Two hand-written lists of the same twenty-two
 * destinations drift the first time a module is added, and the drift is
 * silent — the new screen is simply unreachable from one of them.
 *
 * This is that list. The mobile navigation renders from it.
 *
 * It deliberately does NOT rewrite the sidebar. The sidebar is the desktop
 * portal's only navigation, it carries two submenus and a live-class badge,
 * and reproducing its markup byte for byte from a data source buys nothing
 * today while risking the one navigation that currently works. Instead
 * scripts/student_app_shell_test.php parses the sidebar's rendered HTML and
 * asserts its destinations and this list are the same set. Drift is caught
 * rather than prevented — with no risk to desktop — and the test says so when
 * it fails.
 *
 * Panel gating is the sidebar's own: \App\Models\Field, by slug, status 1.
 */
class StudentNav
{
    /** @var array<string, bool>|null  panel slug => allowed, for this request */
    private static ?array $gates = null;

    /** @var bool|null  memoised, because the bar renders on every page */
    private static ?bool $owes = null;

    /**
     * Every destination, in the sidebar's own order.
     *
     * `patterns` are what makes an item current, in Request::is() form, and
     * they cover more than the item's own URL: a student on
     * student/class-hub/history is still under Classes.
     *
     * @return array<int, array{key:string, label:string, icon:string, route:string, patterns:array<int,string>, gate:?string, group:string}>
     */
    public static function items(): array
    {
        return [
            self::item('dashboard', trans_choice('module_dashboard', 1), 'fas fa-desktop',
                'student.dashboard.index', ['student', 'student/dashboard*'], null, 'home'),

            self::item('class-routine', trans_choice('module_class_routine', 2), 'fas fa-clock',
                'student.class-routine.index', ['student/class-routine*'], 'panel_class_routine', 'academics'),

            self::item('exam-routine', trans_choice('module_exam_routine', 2), 'fas fa-align-left',
                'student.exam-routine.index', ['student/exam-routine*'], 'panel_exam_routine', 'academics'),

            self::item('course-registration', __('Course Registration'), 'fas fa-book',
                'student.course-registration.index', ['student/course-registration*'], null, 'academics'),

            self::item('attendance', trans_choice('module_attendance', 2), 'fas fa-calendar-check',
                'student.attendance.index', ['student/attendance*'], 'panel_attendance', 'academics'),

            self::item('class-hub', 'Class Hub', 'fas fa-broadcast-tower',
                'student.class-hub.index', ['student/class-hub*'], null, 'academics'),

            self::item('assignment', trans_choice('module_assignment', 2), 'fas fa-newspaper',
                'student.assignment.index', ['student/assignment*'], 'panel_assignment', 'academics'),

            self::item('exam-results', __('Exam Results'), 'fas fa-chart-line',
                'student.exam-results.index', ['student/exam-results*'], 'panel_exam_result', 'results'),

            self::item('transcript', trans_choice('module_transcript', 1), 'fas fa-pen-square',
                'student.transcript.index', ['student/transcript*'], 'panel_transcript', 'results'),

            self::item('resit', __('Resits'), 'fas fa-redo-alt',
                'student.resit.index', ['student/resit*'], null, 'results'),

            self::item('form-a2', __('Form A2'), 'fas fa-file-alt',
                'student.form-a2.index', ['student/form-a2*'], null, 'results'),

            self::item('form-a3', __('Form A3 Docs'), 'fas fa-file-contract',
                'student.form-a3.index', ['student/form-a3*'], null, 'results'),

            self::item('fees', trans_choice('module_fees_report', 2), 'fas fa-money-bill-wave',
                'student.fees.index', ['student/fees*'], 'panel_fees_report', 'money'),

            self::item('manual-payment', trans_choice('module_manual_payment', 2), 'fas fa-receipt',
                'student.manual-payment.index', ['student/manual-payment*'], 'panel_fees_report', 'money'),

            self::item('multi-payment', 'Multi-Fee Payments', 'fas fa-layer-group',
                'student.multi-payment.index', ['student/multi-payment*'], 'panel_fees_report', 'money'),

            self::item('payment-plan', 'Payment Plans', 'fas fa-calendar-check',
                'student.payment-plan.index', ['student/payment-plan*', 'student/installment-payment*'],
                'panel_fees_report', 'money'),

            self::item('library', trans_choice('module_library', 2), 'fas fa-book-open',
                'student.library.index', ['student/library*'], 'panel_library', 'library'),

            self::item('e-library', 'E-Library', 'fas fa-book-reader',
                'student.e-library.index', ['student/e-library*'], null, 'library'),

            self::item('notice', trans_choice('module_notice', 2), 'fas fa-bullhorn',
                'student.notice.index', ['student/notice*'], 'panel_notice', 'school'),

            self::item('download', trans_choice('module_download', 2), 'fas fa-download',
                'student.download.index', ['student/download*'], 'panel_download', 'school'),

            self::item('leave', trans_choice('module_apply_leave', 2), 'far fa-edit',
                'student.leave.index', ['student/leave*'], 'panel_leave', 'school'),

            self::item('profile', trans_choice('module_profile', 2), 'fas fa-id-card',
                'student.profile.index', ['student/profile*'], 'panel_profile', 'account'),
        ];
    }

    /** Only what this installation has switched on. */
    public static function available(): array
    {
        return array_values(array_filter(self::items(), fn ($i) => self::allowed($i['gate'])));
    }

    /**
     * The four a student opens daily, plus More.
     *
     * Each tab reaches further than the item it links to: tapping Classes goes
     * to the timetable, but attendance, the class hub and course registration
     * are all under it, so the bar does not lose the student's place when they
     * are three screens in.
     *
     * A tab whose destination is switched off is dropped and the bar lays out
     * what remains, rather than leaving a gap or linking to a 403.
     *
     * @return array<int, array{key:string, label:string, icon:string, url:string, active:bool, badge:bool}>
     */
    public static function tabs(): array
    {
        $by = array_column(self::items(), null, 'key');

        $spec = [
            ['dashboard', __('nav_home'), 'fas fa-home', ['student', 'student/dashboard*']],
            ['class-routine', __('nav_classes'), 'fas fa-calendar-day', [
                'student/class-routine*', 'student/class-hub*', 'student/attendance*',
                'student/course-registration*', 'student/assignment*', 'student/exam-routine*',
            ]],
            ['exam-results', __('nav_results'), 'fas fa-chart-line', [
                'student/exam-results*', 'student/transcript*', 'student/resit*',
                'student/form-a2*', 'student/form-a3*',
            ]],
            ['fees', __('nav_fees'), 'fas fa-wallet', [
                'student/fees*', 'student/payment-plan*', 'student/manual-payment*',
                'student/multi-payment*', 'student/installment-payment*',
            ]],
        ];

        $tabs = [];

        foreach ($spec as [$key, $label, $icon, $patterns]) {
            if (!isset($by[$key]) || !self::allowed($by[$key]['gate'])) {
                continue;
            }

            $tabs[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'url' => $by[$key]['url'],
                'active' => self::matches($patterns),
                'badge' => $key === 'fees' && self::owesMoney(),
            ];
        }

        return $tabs;
    }

    /**
     * Everything the bar does not already reach directly.
     *
     * Grouped as the sheet shows it. A student on Attendance sees Classes lit
     * in the bar; Attendance itself is here, which is where they go to return
     * to it.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function moreGroups(): array
    {
        $inBar = array_column(self::tabs(), 'key');

        $labels = [
            'academics' => __('nav_group_academics'),
            'results' => __('nav_group_results'),
            'money' => __('nav_group_money'),
            'library' => __('nav_group_library'),
            'school' => __('nav_group_school'),
            'account' => __('nav_group_account'),
        ];

        $groups = [];

        foreach (self::available() as $item) {
            if (in_array($item['key'], $inBar, true) || $item['group'] === 'home') {
                continue;
            }

            $item['active'] = self::matches($item['patterns']);
            $groups[$labels[$item['group']] ?? $item['group']][] = $item;
        }

        return $groups;
    }

    /**
     * Is anything still owed on the enrolment the student is looking at?
     *
     * One exists() on an indexed column, memoised, because the bar renders on
     * every page of the portal. A dot rather than a figure: the figure belongs
     * on the Fees screen, and counting rows costs more than asking whether
     * there is one.
     */
    public static function owesMoney(): bool
    {
        if (self::$owes !== null) {
            return self::$owes;
        }

        $enrollment = session('student_enrollment_id');

        if (!$enrollment) {
            return self::$owes = false;
        }

        return self::$owes = \App\Models\Fee::where('student_enroll_id', $enrollment)
            ->where('status', '!=', 1)
            ->exists();
    }

    /** Forget what was memoised — for a test that renders twice. */
    public static function forget(): void
    {
        self::$gates = null;
        self::$owes = null;
    }

    /**
     * Is this panel switched on?
     *
     * Read once per request rather than once per item: the sidebar's own
     * helper queries the fields table for every entry it draws.
     *
     * A gate whose row does not exist is treated as OPEN. The sidebar's
     * `panel('x')->status` would fatal on a missing row, which is not
     * behaviour worth reproducing, and `panel_exam_result` is already handled
     * that way there deliberately.
     */
    protected static function allowed(?string $slug): bool
    {
        if ($slug === null) {
            return true;
        }

        if (self::$gates === null) {
            self::$gates = Field::where('slug', 'like', 'panel_%')
                ->pluck('status', 'slug')
                ->map(fn ($status) => (int) $status === 1)
                ->all();
        }

        return self::$gates[$slug] ?? true;
    }

    /** @param array<int, string> $patterns */
    protected static function matches(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Request::is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $patterns */
    protected static function item(string $key, string $label, string $icon, string $route,
                                   array $patterns, ?string $gate, string $group): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'url' => route($route),
            'patterns' => $patterns,
            'gate' => $gate,
            'group' => $group,
        ];
    }
}
