<?php

namespace Database\Seeders;

use App\Models\ResitRequest;
use App\Models\ResitRequestWorkflowLog;
use App\Models\Semester;
use App\Models\Session;
use App\Models\StudentEnroll;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Services\Resit\ResitEnrollmentService;
use App\Services\Resit\ResitFeeService;

class ResitRequestSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $enroll = StudentEnroll::whereHas('subjects')
            ->with(['subjects', 'session'])
            ->first();

        if (!$enroll) {
            $this->command?->warn('No student enrolments with subjects found. Skipping sample resit requests.');
            return;
        }

        $subject = $enroll->subjects->first();
        if (!$subject) {
            $this->command?->warn('Selected enrolment does not have any attached subjects.');
            return;
        }

        $session = $enroll->session ?? Session::orderByDesc('id')->first();
        if (!$session) {
            $this->command?->warn('No academic session found to attach to sample resit requests.');
            return;
        }

        $resitSession = Session::whereKeyNot($session->id)->orderByDesc('id')->first() ?? $session;
        $resitSemester = Semester::resit()->orderBy('title')->first();

        if (!$resitSemester) {
            $resitSemester = Semester::firstOrCreate(
                ['title' => 'Resit Semester'],
                [
                    'year' => $session->title ?? 'Resit',
                    'status' => 1,
                    'is_resit' => true,
                ]
            );
        }

        if ($resitSemester && !$resitSemester->is_resit) {
            $resitSemester->is_resit = true;
            $resitSemester->save();
        }

        if ($resitSemester && $enroll->program_id) {
            $resitSemester->programs()->syncWithoutDetaching([$enroll->program_id]);
        }

        if (!$resitSemester) {
            $this->command?->warn('Unable to resolve a resit semester for sample data.');
            return;
        }
        $user = User::first();
        $userId = $user?->id;
        $now = Carbon::now();

        $defaultFee = ResitRequest::defaultFee();
    $feeService = app(ResitFeeService::class);
    $enrollmentService = app(ResitEnrollmentService::class);

        $samples = [
            [
                'noteKey' => 'Sample Resit Request - Awaiting Payment',
                'attributes' => [
                    'workflow_state' => ResitRequest::STATE_AWAITING_PAYMENT,
                    'payment_status' => ResitRequest::PAYMENT_PENDING,
                    'fee_amount' => $defaultFee,
                    'state_changed_at' => $now,
                    'state_changed_by' => $userId,
                ],
                'logs' => [
                    [
                        'from_state' => ResitRequest::STATE_REQUESTED,
                        'to_state' => ResitRequest::STATE_AWAITING_PAYMENT,
                        'notes' => 'Sample transition into awaiting payment.',
                    ],
                ],
                'fee' => static function ($fee, ResitRequest $request) use ($feeService, $defaultFee): void {
                    $fee->forceFill([
                        'fee_amount' => $defaultFee,
                        'paid_amount' => 0,
                        'status' => 0,
                        'pay_date' => null,
                    ])->save();

                    $feeService->syncFromFee($fee, $request);
                },
            ],
            [
                'noteKey' => 'Sample Resit Request - Approved',
                'attributes' => [
                    'workflow_state' => ResitRequest::STATE_APPROVED,
                    'payment_status' => ResitRequest::PAYMENT_PAID,
                    'fee_amount' => $defaultFee,
                    'approved_by' => $userId,
                    'approved_at' => $now->copy()->subDay(),
                    'state_changed_at' => $now,
                    'state_changed_by' => $userId,
                ],
                'logs' => [
                    [
                        'from_state' => ResitRequest::STATE_REQUESTED,
                        'to_state' => ResitRequest::STATE_AWAITING_PAYMENT,
                        'notes' => 'Sample request moved to awaiting payment.',
                        'changed_at' => $now->copy()->subDays(2),
                    ],
                    [
                        'from_state' => ResitRequest::STATE_AWAITING_PAYMENT,
                        'to_state' => ResitRequest::STATE_APPROVED,
                        'notes' => 'Payment confirmed and approved.',
                    ],
                ],
                'fee' => static function ($fee, ResitRequest $request) use ($feeService, $defaultFee, $now): void {
                    $fee->forceFill([
                        'fee_amount' => $defaultFee,
                        'paid_amount' => $defaultFee,
                        'status' => 1,
                        'pay_date' => $now->copy()->subDay()->toDateString(),
                    ])->save();

                    $feeService->syncFromFee($fee, $request);
                },
            ],
            [
                'noteKey' => 'Sample Resit Request - Scheduled',
                'attributes' => [
                    'workflow_state' => ResitRequest::STATE_SCHEDULED,
                    'payment_status' => ResitRequest::PAYMENT_PAID,
                    'fee_amount' => $defaultFee,
                    'approved_by' => $userId,
                    'approved_at' => $now->copy()->subDays(3),
                    'state_changed_at' => $now,
                    'state_changed_by' => $userId,
                    'resit_session_id' => $resitSession->id,
                    'resit_semester_id' => $resitSemester->id,
                    'resit_semester_id' => $resitSemester->id,
                    'resit_semester_id' => $resitSemester->id,
                ],
                'logs' => [
                    [
                        'from_state' => ResitRequest::STATE_REQUESTED,
                        'to_state' => ResitRequest::STATE_AWAITING_PAYMENT,
                        'notes' => 'Initial review completed.',
                        'changed_at' => $now->copy()->subDays(4),
                    ],
                    [
                        'from_state' => ResitRequest::STATE_AWAITING_PAYMENT,
                        'to_state' => ResitRequest::STATE_APPROVED,
                        'notes' => 'Approved after payment.',
                        'changed_at' => $now->copy()->subDays(2),
                    ],
                    [
                        'from_state' => ResitRequest::STATE_APPROVED,
                        'to_state' => ResitRequest::STATE_SCHEDULED,
                        'notes' => 'Resit scheduled for upcoming session.',
                    ],
                ],
                'fee' => static function ($fee, ResitRequest $request) use ($feeService, $defaultFee, $now): void {
                    $fee->forceFill([
                        'fee_amount' => $defaultFee,
                        'paid_amount' => $defaultFee,
                        'status' => 1,
                        'pay_date' => $now->copy()->subDays(3)->toDateString(),
                    ])->save();

                    $feeService->syncFromFee($fee, $request);
                },
            ],
        ];

        foreach ($samples as $sample) {
            $request = ResitRequest::updateOrCreate(
                ['notes' => $sample['noteKey']],
                array_merge([
                    'student_enroll_id' => $enroll->id,
                    'subject_id' => $subject->id,
                    'session_id' => $session->id,
                    'payment_id' => null,
                ], $sample['attributes'], ['notes' => $sample['noteKey']])
            );

            $fee = $feeService->ensureFee($request);

            if (!empty($sample['fee']) && is_callable($sample['fee'])) {
                ($sample['fee'])($fee, $request);
                $request->refresh();
            }

            if ($request->workflow_state === ResitRequest::STATE_SCHEDULED) {
                $enrollmentService->ensureEnrollment($request);
            }

            if (!empty($sample['logs'])) {
                ResitRequestWorkflowLog::where('resit_request_id', $request->id)->delete();

                foreach ($sample['logs'] as $logData) {
                    ResitRequestWorkflowLog::create([
                        'resit_request_id' => $request->id,
                        'from_state' => $logData['from_state'] ?? null,
                        'to_state' => $logData['to_state'],
                        'changed_by' => $userId,
                        'notes' => $logData['notes'] ?? null,
                        'changed_at' => $logData['changed_at'] ?? Carbon::now(),
                        'meta' => null,
                    ]);
                }
            }
        }

        $this->command?->info('Sample resit requests generated.');
    }
}
