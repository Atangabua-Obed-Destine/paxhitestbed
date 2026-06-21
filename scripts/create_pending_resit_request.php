<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ResitRequest;
use App\Models\StudentEnroll;
use Illuminate\Support\Carbon;

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

$notes = 'CLI generated pending resit request at ' . Carbon::now()->toDateTimeString();

$request = ResitRequest::create([
    'student_enroll_id' => $enroll->id,
    'subject_id' => $subject->id,
    'session_id' => $enroll->session_id,
    'fee_amount' => ResitRequest::defaultFee(),
    'payment_status' => ResitRequest::PAYMENT_PENDING,
    'workflow_state' => ResitRequest::STATE_REQUESTED,
    'notes' => $notes,
]);

$request->refresh();

$fee = $request->fee;

if ($fee) {
    echo sprintf(
        'Created resit request #%d in state %s with payment status %s. Fee #%d for %.2f remains unpaid.' . PHP_EOL,
        $request->id,
        $request->workflow_state,
        $request->payment_status,
        $fee->id,
        $fee->fee_amount
    );
} else {
    echo sprintf(
        'Created resit request #%d in state %s with payment status %s but fee record is missing.' . PHP_EOL,
        $request->id,
        $request->workflow_state,
        $request->payment_status
    );
}
