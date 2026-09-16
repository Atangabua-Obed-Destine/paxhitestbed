<?php
/**
 * An admission is approved on the system, by the people who hold the steps.
 *
 * It used to be approved on paper: the application form printed a "For Official
 * Use Only" block whose lines — received by, documents verified by, the
 * decision, the registrar's signature — were filled in by hand and never came
 * back into the system. Meanwhile the stage was a free dropdown in two places,
 * so anyone who could edit an application could mark it approved, and the
 * button that turns an application into a student had no gate on it at all.
 *
 * What has to hold now:
 *   - a step is given only by somebody who holds its permission;
 *   - the steps are given in order, so a final approval cannot precede the
 *     document check;
 *   - no student record exists until the final approval does, and that is
 *     refused on the server, not by hiding a button;
 *   - a missing document is Returned, not Refused, and a refusal must say why;
 *   - the applications approved before any of this existed stay usable.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/application_approval_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\ApplicationApproval;
use App\Models\Student;
use App\Services\ApplicationApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$service = app(ApplicationApprovalService::class);

$superAdmin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
    ->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->firstOrFail();

/** A fresh application that has collected no approvals. */
// The fixture is shared with the other admission suites, which were starved the
// same way: scripts/support/application_fixtures.php.
require_once __DIR__ . '/support/application_fixtures.php';

echo "\n== The chain is defined where the stages are ==\n";

$steps = Application::approvalStepMap();

check('there are four steps', count($steps) === 4, implode(', ', array_keys($steps)));
check('they are in order', array_column($steps, 'sequence') === [1, 2, 3, 4]);
check('each names the permission that authorises it',
    collect($steps)->every(fn ($s) => str_starts_with($s['permission'], 'application-approve-')));
check('the first two cannot refuse an applicant',
    $steps['received']['can_reject'] === false && $steps['documents']['can_reject'] === false);
check('the board and the final approval can',
    $steps['board']['can_reject'] === true && $steps['final']['can_reject'] === true);
check('the board deliberation is not shown to the applicant', $steps['board']['visible'] === false);
check('every step has a title', collect(array_keys($steps))->every(
    fn ($k) => Application::approvalStepTitle($k) !== 'application_approval.' . $k));

echo "\n== The permissions exist and are not handed to everybody ==\n";

foreach ($steps as $key => $definition) {
    $permission = DB::table('permissions')->where('name', $definition['permission'])->first();

    check("{$definition['permission']} exists", $permission !== null);

    if ($permission) {
        $roles = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('permission_id', $permission->id)
            ->pluck('roles.name');

        // Mirroring application-edit would have handed the final say on
        // admissions to eight roles, Human Resource among them.
        check("  and is not granted to every role that can edit an application",
            $roles->count() <= 2, $roles->implode(', '));
    }
}

echo "\n== A step is given only by somebody who holds it ==\n";

$application = pendingApplication();

if (!$application) {
    echo "  SKIP  no application without approvals to work with\n";
} else {
    DB::beginTransaction();

    try {
        // A real member of staff, deliberately not an admin: Gate::before on
        // is_admin would otherwise wave every permission check through.
        $outsider = App\User::where('is_admin', 0)->where('status', '1')->first();

        if ($outsider) {
            $outsider->revokePermissionTo(collect($steps)->pluck('permission')->all());
            app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            $refused = false;

            try {
                $service->approve($application->fresh(), 'received', $outsider->fresh());
            } catch (ValidationException $e) {
                $refused = true;
            }

            check('a user without the permission is refused', $refused);
            check('and nothing was written',
                ApplicationApproval::where('application_id', $application->id)->count() === 0);
        } else {
            echo "  SKIP  no non-admin staff account to test with\n";
        }
    } finally {
        DB::rollBack();
    }
}

echo "\n== The steps are given in order ==\n";

if ($application) {
    DB::beginTransaction();

    try {
        $fresh = $application->fresh();

        $refused = false;
        $message = '';

        try {
            $service->approve($fresh, 'final', $superAdmin);
        } catch (ValidationException $e) {
            $refused = true;
            $message = collect($e->errors())->flatten()->first();
        }

        check('the final approval cannot be given first', $refused);
        check('and the message says which step is waiting',
            str_contains($message, Application::approvalStepTitle('received')), $message);
        check('nothing was written', ApplicationApproval::where('application_id', $application->id)->count() === 0);

        // In order, all four.
        foreach (array_keys($steps) as $key) {
            $service->approve($application->fresh(), $key, $superAdmin);
        }

        $done = $application->fresh();

        check('given in order, all four are accepted',
            ApplicationApproval::where('application_id', $application->id)->count() === 4);
        check('the application is fully approved', $done->isFullyApproved());
        check('nothing is left waiting', $done->currentApprovalStep() === null);
        check('and the stage followed the approvals', $done->stage === 'decision_approved',
            $done->stage);
        check('the decision was dated', $done->decision_at !== null);
        // The applications list offers the acceptance letter, and filters by
        // Approved, on this column — removing the old decision dropdown would
        // otherwise have quietly stopped both working.
        check('the application is marked approved for the list and the letter',
            (int) $done->status === 2, 'status ' . $done->status);
    } finally {
        DB::rollBack();
    }
}

echo "\n== The signature is a snapshot, not a lookup ==\n";

if ($application) {
    DB::beginTransaction();

    try {
        $service->approve($application->fresh(), 'received', $superAdmin);
        $row = ApplicationApproval::where('application_id', $application->id)->first();

        check('the name is written into the record', $row->signed_name === $superAdmin->name,
            (string) $row->signed_name);
        check('and it survives the person being renamed', (function () use ($superAdmin, $row) {
            DB::table('users')->where('id', $superAdmin->id)->update(['first_name' => 'Renamed']);

            return $row->fresh()->signatory() === $superAdmin->name;
        })(), 'the record must keep saying what was true when it was signed');
    } finally {
        DB::rollBack();
    }
}

echo "\n== Returning clears what came after it ==\n";

if ($application) {
    DB::beginTransaction();

    try {
        foreach (['received', 'documents', 'board'] as $key) {
            $service->approve($application->fresh(), $key, $superAdmin);
        }

        check('three steps stand before the return', $application->fresh()->hasApproved('documents'));

        $service->returnTo($application->fresh(), 'final', 'documents', $superAdmin, 'The transcript is unreadable.');

        $returned = $application->fresh();

        check('the returned step is outstanding again', !$returned->hasApproved('documents'));
        check('and so is everything after it', !$returned->hasApproved('board'));
        check('but the step before it still stands', $returned->hasApproved('received'));
        check('the application is waiting on the returned step',
            $returned->currentApprovalStep() === 'documents', (string) $returned->currentApprovalStep());
        check('it is not convertible', !$returned->isFullyApproved());
        check('and the applicant is asked for documents', $returned->stage === 'documents_required',
            $returned->stage);
        check('the history keeps the approvals that were crossed out',
            $returned->approvals()->count() === 4);

        $refused = false;

        try {
            $service->returnTo($application->fresh(), 'documents', 'board', $superAdmin, 'forward');
        } catch (ValidationException $e) {
            $refused = true;
        }

        check('an application cannot be returned forwards', $refused);
    } finally {
        DB::rollBack();
    }
}

echo "\n== A refusal must say why, and only where it is allowed ==\n";

if ($application) {
    DB::beginTransaction();

    try {
        $refused = false;

        try {
            $service->reject($application->fresh(), 'received', $superAdmin, 'Not good enough');
        } catch (ValidationException $e) {
            $refused = true;
        }

        check('the receipt step cannot refuse an applicant', $refused);

        foreach (['received', 'documents'] as $key) {
            $service->approve($application->fresh(), $key, $superAdmin);
        }

        $noReason = false;

        try {
            $service->reject($application->fresh(), 'board', $superAdmin, '   ');
        } catch (ValidationException $e) {
            $noReason = true;
        }

        check('a refusal with no reason is refused', $noReason);

        $service->reject($application->fresh(), 'board', $superAdmin, 'Does not meet the entry requirements.');
        $rejected = $application->fresh();

        check('the board can refuse', $rejected->isApprovalRejected());
        check('the application is not convertible', !$rejected->isFullyApproved());
        check('the stage says so', $rejected->stage === 'decision_rejected', $rejected->stage);
        check('and nothing is waiting on anybody', $rejected->currentApprovalStep() === null);

        $blocked = false;

        try {
            $service->approve($application->fresh(), 'final', $superAdmin);
        } catch (ValidationException $e) {
            $blocked = true;
        }

        check('a refused application cannot then be approved', $blocked);
    } finally {
        DB::rollBack();
    }
}

echo "\n== No student record without the final approval ==\n";

// This is the gate that matters: posted straight at the controller, with a
// complete and otherwise valid payload, so it cannot pass merely because a
// button was hidden.
$target = pendingApplication();

if (!$target) {
    echo "  SKIP  no application without approvals to convert\n";
} else {
    Auth::guard('web')->login($superAdmin);
    $session = app('session.store');
    $session->start();

    $program = App\Models\Program::find($target->program_id) ?: App\Models\Program::first();
    $batch = App\Models\Batch::first();
    $semester = App\Models\Semester::first();
    $section = App\Models\Section::first();
    $sessionRow = App\Models\Session::first();

    $payload = [
        '_token' => $session->token(),
        'registration_no' => $target->registration_no,
        'batch' => $batch->id,
        'program' => $program->id,
        'session' => $sessionRow->id,
        'semester' => $semester->id,
        'section' => $section->id,
        'first_name' => $target->first_name ?: 'Approval',
        'last_name' => $target->last_name ?: 'Test',
        'email' => 'approval.test.' . uniqid() . '@example.test',
        'phone' => '000000000',
        'gender' => $target->gender ?: 1,
        'dob' => '2000-01-01',
        'admission_date' => now()->format('Y-m-d'),
    ];

    // The conversion modal's own action. Posting anywhere else would let this
    // "refusal" be somebody else's validation error — which is exactly what
    // happened the first time this test was written, against the student
    // controller, where a missing faculty field made the gate look like it was
    // working when it had not been reached.
    // The conversion modal's own action, as a path. route() would return it
    // with the installation's base directory on the front, which
    // Request::create treats as part of the path — the kernel then 404s and a
    // "refusal" means only that nothing was reached.
    $post = function (array $payload) use ($kernel, $session) {
        $request = Illuminate\Http\Request::create('/admin/admission/application', 'POST', $payload);
        $request->setLaravelSession($session);

        return $kernel->handle($request);
    };

    DB::beginTransaction();

    try {
        $studentsBefore = Student::count();
        $response = $post($payload);
        $studentsAfter = Student::count();

        // Before anything else: the request has to have reached the controller.
        // A 404 creates no student either, and would pass every check below
        // while proving nothing at all.
        check('the conversion route was actually reached',
            $response->getStatusCode() !== 404, 'status ' . $response->getStatusCode());

        check('the conversion is refused while approvals are outstanding',
            $studentsAfter === $studentsBefore,
            $studentsBefore . ' → ' . $studentsAfter . ' (status ' . $response->getStatusCode() . ')');
        check('and no enrolment was created either',
            !App\Models\StudentEnroll::whereHas('student', fn ($q) => $q->where('email', $payload['email']))->exists());

        // It has to be refused for the RIGHT reason. A validation error would
        // create no student either, and would pass the two checks above while
        // the gate itself did nothing.
        check('and it was the approvals that refused it, not a validation error',
            !$session->has('errors')
            || collect($session->get('errors')->getBags()['default']->all())->isEmpty(),
            $session->has('errors')
                ? implode('; ', $session->get('errors')->getBags()['default']->all())
                : '');

        // Now approve it properly, and the very same payload must go through.
        foreach (array_keys($steps) as $key) {
            $service->approve($target->fresh(), $key, $superAdmin);
        }

        check('the application is now fully approved', $target->fresh()->isFullyApproved());

        $response = $post($payload);
        $created = Student::where('email', $payload['email'])->first();

        check('the same conversion now succeeds', $created !== null,
            'status ' . $response->getStatusCode());
        check('and the student was given a matricule', $created && filled($created->student_id),
            (string) optional($created)->student_id);
    } finally {
        DB::rollBack();
    }
}

echo "\n== The applicant sees the progress, not the deliberation ==\n";

if ($application) {
    DB::beginTransaction();

    try {
        foreach (['received', 'documents', 'board'] as $key) {
            $service->approve($application->fresh(), $key, $superAdmin, 'Internal note for ' . $key);
        }

        $visibleToApplicant = $application->statusUpdates()
            ->where('is_visible_to_applicant', true)
            ->pluck('title')
            ->all();

        $hidden = $application->statusUpdates()
            ->where('is_visible_to_applicant', false)
            ->pluck('title')
            ->all();

        check('the board deliberation is kept off the applicant\'s timeline',
            in_array(Application::approvalStepTitle('board'), $hidden, true),
            'hidden: ' . implode(', ', $hidden));
        check('but the earlier steps are shown',
            in_array(Application::approvalStepTitle('received'), $visibleToApplicant, true),
            'visible: ' . implode(', ', $visibleToApplicant));
        check('and the applicant is told a decision is pending',
            $application->fresh()->stage === 'decision_pending', $application->fresh()->stage);
    } finally {
        DB::rollBack();
    }
}

echo "\n== The ways round it are gone ==\n";

$editView = file_get_contents(__DIR__ . '/../resources/views/admin/application/edit.blade.php');
$timelineView = file_get_contents(__DIR__ . '/../resources/views/admin/application/partials/timeline.blade.php');

check('the edit form has no stage control',
    preg_match('/<(select|input)[^>]*name="stage"/', $editView) === 0);
check('and no progress control',
    preg_match('/<(select|input)[^>]*name="progress"/', $editView) === 0);
check('the Add update form has no stage control',
    preg_match('/<(select|input)[^>]*name="stage"/', $timelineView) === 0);
check('and no decision override',
    preg_match('/<select[^>]*name="status"/', $timelineView) === 0);

$controller = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/ApplicationController.php');

check('update() no longer reads a stage from the request',
    !str_contains($controller, "\$application->stage = \$validated['stage']"));
check('storeStatusUpdate() no longer sets a stage',
    !str_contains($controller, "\$validated['stage'],"));

// Posted anyway, by hand, they must still do nothing.
if ($application) {
    DB::beginTransaction();

    try {
        Auth::guard('web')->login($superAdmin);
        $session = app('session.store');
        $session->start();

        $before = $application->fresh();

        $request = Illuminate\Http\Request::create(
            '/admin/admission/application/' . $application->id . '/status-update',
            'POST',
            [
                '_token' => $session->token(),
                'note' => 'A note, with a stage smuggled in.',
                'stage' => 'decision_approved',
                'status' => 2,
                'progress' => 100,
            ]
        );
        $request->setLaravelSession($session);
        $kernel->handle($request);

        $after = $application->fresh();

        check('posting a stage to the note form does not move the application',
            $after->stage === $before->stage, $before->stage . ' → ' . $after->stage);
        check('and does not approve it', !$after->isFullyApproved());
        check('the note itself was still recorded',
            $after->statusUpdates()->where('note', 'A note, with a stage smuggled in.')->exists());
    } finally {
        DB::rollBack();
    }
}

echo "\n== The applications approved before any of this stay usable ==\n";

$alreadyApproved = Application::where('stage', 'decision_approved')->get();

check('every application that was already approved carries an approval',
    $alreadyApproved->every(fn ($a) => $a->approvals()->exists()),
    $alreadyApproved->count() . ' applications');
check('and each is convertible',
    $alreadyApproved->every(fn ($a) => $a->isFullyApproved()));
check('the backfilled record says where it came from',
    (bool) ApplicationApproval::where('signed_name', 'Approved before the approval flow existed')->exists());
check('it is dated from the decision it records',
    (function () {
        $row = ApplicationApproval::whereNull('decided_by')->first();

        if (!$row) {
            return false;
        }

        $application = Application::find($row->application_id);

        return $application->decision_at === null
            || $row->decided_at->equalTo($application->decision_at);
    })());

echo "\n== The board's record can finally be written ==\n";

$boardForm = preg_match('/name="board_review\[/', $editView) === 1;

check('the board review form is on the page', $boardForm);
check('it is no longer gated on a Field toggle that does not exist',
    !str_contains($editView, "fieldEnabled('application_board_review')"));
check('the toggle really is absent, which is why it never saved',
    DB::table('fields')->where('slug', 'application_board_review')->doesntExist());
check('it is gated on the board approval instead',
    str_contains($editView, "@can('application-approve-board')"));

echo "\n== A step is offered only to whoever holds it ==\n";

// The panel is on a page that anyone with application-edit can open. What each
// of them may do there has to differ.
$viewer = App\User::where('is_admin', 0)->where('status', '1')->first();
$pendingForPanel = pendingApplication();

if (!$viewer || !$pendingForPanel) {
    echo "  SKIP  need a non-admin staff account and an unapproved application\n";
} else {
    DB::beginTransaction();

    try {
        $viewer->givePermissionTo('application-view', 'application-edit');
        $viewer->revokePermissionTo(collect($steps)->pluck('permission')->all());
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Auth::guard('web')->login($viewer->fresh());

        $render = function () use ($kernel, $pendingForPanel) {
            $request = Illuminate\Http\Request::create(
                '/admin/admission/application/' . $pendingForPanel->id . '/edit',
                'GET'
            );
            $request->setLaravelSession(app('session.store'));

            return $kernel->handle($request);
        };

        $response = $render();
        $html = $response->getContent();

        check('a colleague without any approval permission can still open the page',
            $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
        check('and sees the approvals', str_contains($html, __('Admission approvals')));
        check('but is offered no Approve button',
            !str_contains($html, 'approve-received'), 'the approve form must not be rendered');
        check('and is told who it is waiting for',
            str_contains($html, __('Waiting for whoever holds this step. You do not hold it.')));
        check('the board\'s record is not shown to them either',
            !str_contains($html, 'board_review['));

        // Now give them exactly one step.
        $viewer->givePermissionTo('application-approve-receipt');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::guard('web')->login($viewer->fresh());

        $html = $render()->getContent();

        check('holding the receipt step offers its Approve form',
            str_contains($html, 'approve-received'));
        check('the receipt step still cannot refuse an applicant',
            !str_contains($html, 'reject-received'));
        check('and the board\'s record stays hidden from them',
            !str_contains($html, 'board_review['));
    } finally {
        DB::rollBack();
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::guard('web')->login($superAdmin);
    }
}

echo "\n== The printed block prints the record ==\n";

$previewView = file_get_contents(__DIR__ . '/../resources/views/admin/application/preview.blade.php');

check('the official-use block no longer prints empty rules for the signatures',
    !str_contains($previewView, '{{ __(\'Application received by\') }}</span><span class="box"></span>'));
check('it prints who approved each step', str_contains($previewView, 'signatory()'));

if ($alreadyApproved->isNotEmpty()) {
    Auth::guard('web')->login($superAdmin);

    $request = Illuminate\Http\Request::create(
        '/admin/admission/application/' . $alreadyApproved->first()->id . '/preview',
        'GET'
    );
    $request->setLaravelSession(app('session.store'));
    $html = $kernel->handle($request)->getContent();

    check('an approved application prints as accepted',
        str_contains($html, __('application_stage.decision_approved')));
    check('and names the final approval step',
        str_contains($html, Application::approvalStepTitle('final')));
}

echo "\n== The applications list shows where each application stands ==\n";

// An admin working through the list needs to see which applications are
// waiting, and on which step, without opening each one.

$indexView = file_get_contents(__DIR__ . '/../resources/views/admin/application/index.blade.php');

check('the list has an Approval column', str_contains($indexView, "<th>{{ __('Approval') }}</th>"));
check('each row renders its approval summary', str_contains($indexView, 'approvalSummary()'));
// The list's query now lives in ApplicationListFilter, shared with the
// admissions board report, so that is where the eager load is looked for.
check('the approvals are loaded with the list, not per row',
    in_array('approvals', App\Support\ApplicationListFilter::LIST_RELATIONS, true)
    && str_contains($controller, 'ApplicationListFilter::fromRequest'));

// The rule on real kinds of application.
$draft = Application::where('stage', 'draft')->whereDoesntHave('approvals')->first();

if ($draft) {
    check('a draft reads as not submitted, not as waiting',
        $draft->approvalSummary()['state'] === 'not_submitted', $draft->approvalSummary()['label']);
}

$backfilled = Application::whereHas('approvals', fn ($q) => $q->whereNull('decided_by'))->with('approvals')->first();

if ($backfilled) {
    $summary = $backfilled->approvalSummary();
    check('an application approved before the flow says so', $summary['state'] === 'approved_before_flow', $summary['label']);
    check('and is not shown as four signatures', $summary['done'] === 1, $summary['done'] . '/4');
}

// Every state, staged on one application and rolled back.
$staged = pendingApplication();

if ($staged) {
    $state = fn () => Application::with('approvals')->find($staged->id)->approvalSummary();

    DB::beginTransaction();

    try {
        check('untouched, it waits on the first step',
            $state()['state'] === 'waiting' && $state()['step'] === 'received' && $state()['done'] === 0,
            $state()['label']);

        $service->approve($staged->fresh(), 'received', $superAdmin);
        check('after receipt it waits on the documents, one of four done',
            $state()['step'] === 'documents' && $state()['done'] === 1, $state()['label']);
        check('the label names the step it waits on',
            $state()['label'] === __('Waiting on :step', ['step' => Application::approvalStepTitle('documents')]),
            $state()['label']);

        $service->approve($staged->fresh(), 'documents', $superAdmin);
        $service->returnTo($staged->fresh(), 'board', 'documents', $superAdmin, 'Transcript unreadable.');
        check('a returned application is marked as returned, in amber',
            $state()['state'] === 'returned' && $state()['tone'] === 'warning', $state()['label']);
        check('and keeps the receipt that still stands',
            $state()['done'] === 1 && $state()['steps']['received']['approved'], $state()['done'] . '/4');

        // The list itself, rendered over the uncommitted rows on this connection.
        Auth::guard('web')->login($superAdmin);

        $queries = [];
        $listening = true;
        DB::listen(function ($query) use (&$queries, &$listening) {
            if ($listening) {
                $queries[] = $query->sql;
            }
        });

        $request = Illuminate\Http\Request::create(
            '/admin/admission/application?degree_type=0&session=0&program=0&status=&start_date='
                . now()->subYear()->toDateString() . '&end_date=' . now()->toDateString() . '&registration_no=&applicant=',
            'GET'
        );
        $request->setLaravelSession(app('session.store'));
        $response = $kernel->handle($request);
        $listening = false;
        $html = $response->getContent();

        check('the list renders', $response->getStatusCode() === 200, (string) $response->getStatusCode());
        check('the Approval heading is on the page', str_contains($html, '<th>Approval</th>'));
        check('the returned application shows as returned on the page',
            str_contains($html, e(__('Returned for :step', ['step' => Application::approvalStepTitle('documents')]))));
        check('approvals are fetched once for the whole page, not per row',
            count(array_filter($queries, fn ($sql) => str_contains($sql, 'application_approvals'))) === 1,
            count(array_filter($queries, fn ($sql) => str_contains($sql, 'application_approvals'))) . ' queries');

        if (preg_match('/<thead>([\s\S]*?)<\/thead>/', $html, $head)
            && preg_match('/<tbody>\s*<tr[^>]*>([\s\S]*?)<\/tr>/', $html, $firstRow)) {
            check('every row has a cell for every heading',
                substr_count($head[1], '<th') === substr_count($firstRow[1], '<td'),
                substr_count($head[1], '<th') . ' headings, ' . substr_count($firstRow[1], '<td') . ' cells');
        }

        $service->approve($staged->fresh(), 'documents', $superAdmin);
        $service->approve($staged->fresh(), 'board', $superAdmin);
        $service->approve($staged->fresh(), 'final', $superAdmin);
        check('approved through the chain, it reads Approved with all four signed',
            $state()['state'] === 'approved' && $state()['done'] === 4, $state()['label']);
        check('and the tooltip names who signed',
            str_contains($state()['detail'], $superAdmin->name));
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $service->approve($staged->fresh(), 'received', $superAdmin);
        $service->approve($staged->fresh(), 'documents', $superAdmin);
        $service->reject($staged->fresh(), 'board', $superAdmin, 'Does not meet programme requirements.');

        check('a refused application is marked refused, in red, naming the step',
            $state()['state'] === 'refused' && $state()['tone'] === 'danger' && $state()['step'] === 'board',
            $state()['label']);
        check('and still shows the steps given before it was refused',
            $state()['done'] === 2, $state()['done'] . '/4');
    } finally {
        DB::rollBack();
    }
}

echo "\n== The final approval and the student record are one action ==\n";

// Approving the last step IS creating the record: that is where the enrolment
// is made, the fees assigned and the acceptance letter sent. An application can
// no longer sit approved with nobody having created the student — which is
// exactly what happened to the application that prompted this.

$conversionPayload = function (Application $target) {
    $session = app('session.store');
    $session->start();

    $program = App\Models\Program::find($target->program_id) ?: App\Models\Program::first();

    return [
        '_token' => $session->token(),
        'registration_no' => $target->registration_no,
        'batch' => App\Models\Batch::where('status', '1')->orderByDesc('id')->value('id'),
        'program' => $program->id,
        'session' => App\Models\Session::first()->id,
        'semester' => App\Models\Semester::first()->id,
        'section' => App\Models\Section::first()->id,
        'first_name' => $target->first_name ?: 'Final',
        'last_name' => $target->last_name ?: 'Step',
        'email' => 'final.step.' . uniqid() . '@example.test',
        'phone' => '000000000',
        'gender' => $target->gender ?: 1,
        'dob' => '2000-01-01',
        'admission_date' => now()->format('Y-m-d'),
    ];
};

// The same fixture the rest of the suite uses: a pristine application, made
// here when the database has none free.
$convertible = function () {
    $application = pendingApplication();

    return $application && !Student::where('registration_no', $application->registration_no)->exists()
        ? $application
        : null;
};

$waitingOnFinal = $convertible();

if (!$waitingOnFinal) {
    echo "  SKIP  no convertible application\n";
} else {
    DB::beginTransaction();

    try {
        Auth::guard('web')->login($superAdmin);

        // Everything but the last step.
        foreach (['received', 'documents', 'board'] as $step) {
            $service->approve($waitingOnFinal->fresh(), $step, $superAdmin);
        }

        check('before converting, it waits on the final approval',
            $waitingOnFinal->fresh()->currentApprovalStep() === Application::finalApprovalStep());

        $payload = $conversionPayload($waitingOnFinal);
        $request = Illuminate\Http\Request::create('/admin/admission/application', 'POST', $payload);
        $request->setLaravelSession(app('session.store'));
        $kernel->handle($request);

        $created = Student::where('registration_no', $waitingOnFinal->registration_no)->first();
        $fresh = $waitingOnFinal->fresh();

        check('creating the record from the final step works', $created !== null);
        check('and records the final approval in the same act', $fresh->isFullyApproved());
        check('exactly once',
            $fresh->approvals->where('step', Application::finalApprovalStep())->where('decision', 'approved')->count() === 1);
    } finally {
        DB::rollBack();
    }

    // Atomicity: the approval must not survive a conversion that fails.
    DB::beginTransaction();

    try {
        $target = $convertible();
        $takenEmail = Student::whereNotNull('email')->value('email');

        foreach (['received', 'documents', 'board'] as $step) {
            $service->approve($target->fresh(), $step, $superAdmin);
        }

        $payload = $conversionPayload($target);
        $payload['email'] = $takenEmail; // already belongs to a student

        $request = Illuminate\Http\Request::create('/admin/admission/application', 'POST', $payload);
        $request->setLaravelSession(app('session.store'));
        $kernel->handle($request);

        $fresh = $target->fresh();

        check('a conversion that fails creates no student',
            !Student::where('registration_no', $target->registration_no)->exists());
        check('and gives no final approval either',
            !$fresh->isFullyApproved() && $fresh->currentApprovalStep() === Application::finalApprovalStep());
    } finally {
        DB::rollBack();
    }

    // An application already approved converts without being approved twice.
    $alreadyApproved = Application::whereHas('approvals')->get()
        ->first(fn ($row) => $row->isFullyApproved() && !Student::where('registration_no', $row->registration_no)->exists());

    if ($alreadyApproved) {
        DB::beginTransaction();

        try {
            $before = $alreadyApproved->approvals()->count();

            $request = Illuminate\Http\Request::create('/admin/admission/application', 'POST', $conversionPayload($alreadyApproved));
            $request->setLaravelSession(app('session.store'));
            $kernel->handle($request);

            check('an already-approved application still converts',
                Student::where('registration_no', $alreadyApproved->registration_no)->exists());
            check('without recording a second final approval',
                $alreadyApproved->fresh()->approvals()->count() === $before,
                $before . ' before, ' . $alreadyApproved->fresh()->approvals()->count() . ' after');
        } finally {
            DB::rollBack();
        }
    }

    // Nobody without the final step may convert at the final step.
    $withoutFinal = App\User::where('is_admin', 0)->where('status', '1')->get()
        ->first(fn ($user) => $user->can('application-edit') && !$user->can('application-approve-final'));

    if ($withoutFinal) {
        DB::beginTransaction();

        try {
            $target = $convertible();

            foreach (['received', 'documents', 'board'] as $step) {
                $service->approve($target->fresh(), $step, $superAdmin);
            }

            Auth::guard('web')->login($withoutFinal);

            $request = Illuminate\Http\Request::create('/admin/admission/application', 'POST', $conversionPayload($target));
            $request->setLaravelSession(app('session.store'));
            $kernel->handle($request);

            check('someone who does not hold the final step cannot convert at it',
                !Student::where('registration_no', $target->registration_no)->exists());
            check('and no approval is recorded for them', !$target->fresh()->isFullyApproved());
        } finally {
            DB::rollBack();
            Auth::guard('web')->login($superAdmin);
        }
    } else {
        echo "  SKIP  no member of staff holds application-edit without the final step\n";
    }
}

$approvalsPartial = file_get_contents(__DIR__ . '/../resources/views/admin/application/partials/approvals.blade.php');

check('the final step offers the conversion, not a separate approve box',
    str_contains($approvalsPartial, 'Approve & create student record')
    && str_contains($approvalsPartial, "data-bs-target=\"#convertApplicationModal\""));
check('and keeps no second path that could approve it without creating the record',
    !preg_match('~id="approve-\{\{ \$step\[.key.\] \}\}"~', $approvalsPartial)
    || str_contains($approvalsPartial, '@if(!$isFinalStep)'));

// Anything this suite made for itself goes now, so the next run starts where
// this one did.
removeFixtures();

check('the suite left no fixtures of its own behind',
    !Application::where('first_name', 'Fixture')->where('last_name', 'Application')->exists());

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
