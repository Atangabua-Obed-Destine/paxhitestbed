<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ResitRequest;
use App\Models\Semester;
use App\Models\Session;
use App\Models\StudentEnroll;
use App\Services\Resit\ResitFeeService;
use App\Services\Resit\ResitRequestWorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\User;

$enroll = StudentEnroll::with('subjects')
    ->whereHas('subjects')
    ->orderByDesc('id')
    ->first();

if (!$enroll) {
    echo "No student enrollment with subjects found." . PHP_EOL;
    exit(1);
}

$subject = $enroll->subjects->first();
if (!$subject) {
    echo "Selected enrollment does not have any linked subjects." . PHP_EOL;
    exit(1);
}

$resitSemester = Semester::resit()->orderByDesc('id')->first();
if (!$resitSemester) {
    echo "No semester marked as resit is available." . PHP_EOL;
    exit(1);
}

$resitSession = Session::whereKeyNot($enroll->session_id)
    ->orderByDesc('id')
    ->first();

if (!$resitSession) {
    $resitSession = Session::orderByDesc('id')->first();
}

if (!$resitSession) {
    echo "No academic session found to schedule against." . PHP_EOL;
    exit(1);
}

$notes = 'CLI generated resit request at ' . Carbon::now()->toDateTimeString();

$request = ResitRequest::create([
    'student_enroll_id' => $enroll->id,
    'subject_id' => $subject->id,
    'session_id' => $enroll->session_id,
    'fee_amount' => ResitRequest::defaultFee(),
    'payment_status' => ResitRequest::PAYMENT_PENDING,
    'workflow_state' => ResitRequest::STATE_REQUESTED,
    'notes' => $notes,
]);

$feeService = app(ResitFeeService::class);
$fee = $feeService->ensureFee($request);

$feeAmount = ResitRequest::defaultFee();
$fee->forceFill([
    'fee_amount' => $feeAmount,
    'paid_amount' => $feeAmount,
    'status' => 1,
    'pay_date' => Carbon::now()->toDateString(),
])->save();

$feeService->syncFromFee($fee, $request);

$user = User::whereHas('roles', function ($query) {
    $query->whereIn('name', ['Super Admin', 'Admin']);
})->first() ?? User::first();

if (!$user) {
    echo "No administrative user found to execute transitions." . PHP_EOL;
    exit(1);
}

Auth::guard('web')->login($user);

$request->refresh();

$workflow = app(ResitRequestWorkflowService::class);
$request = $workflow->transition($request, ResitRequest::STATE_APPROVED);
$request = $workflow->transition($request, ResitRequest::STATE_SCHEDULED, [
    'resit_session_id' => $resitSession->id,
    'resit_semester_id' => $resitSemester->id,
]);

Auth::guard('web')->logout();

$request->refresh()->load(['resitEnroll.subjects']);

echo sprintf(
    'Resit request #%d scheduled for subject %s (%s) into semester %s and session %s.' . PHP_EOL,
    $request->id,
    $subject->code ?? $subject->title,
    $subject->title,
    $resitSemester->title,
    $resitSession->title
);

$resitEnroll = $request->resitEnroll;

if ($resitEnroll) {
    $subjectCodes = $resitEnroll->subjects->pluck('code')->filter()->implode(', ');
    echo sprintf(
        'Linked resit enrollment #%d for student %d with status %d. Subjects: %s' . PHP_EOL,
        $resitEnroll->id,
        $resitEnroll->student_id,
        $resitEnroll->status,
        $subjectCodes ?: 'none'
    );
} else {
    echo 'Resit enrollment record was not attached.' . PHP_EOL;
}
