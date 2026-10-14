    <!-- Edit modal content -->
    <div id="payModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" id="payModal-{{ $row->id }}" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <form class="needs-validation" novalidate action="{{ route($route.'.pay') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Student Info Section -->
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user"></i> {{ __('student_information') }}</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_matricule') }}:</strong> 
                                        <span class="text-primary">#{{ $row->studentEnroll->matricule ?? ($row->studentEnroll->student->student_id ?? '') }}</span>
                                        @if($row->studentEnroll && $row->studentEnroll->program)
                                            <span class="badge" style="background: {{ $row->studentEnroll->program->academic_level == 'M' ? '#f5576c' : ($row->studentEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 8px; margin-left: 5px;">
                                                {{ $row->studentEnroll->program->academic_level == 'A' ? 'UG' : ($row->studentEnroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                            </span>
                                        @endif
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_name') }}:</strong> {{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}</p>
                                    <p class="mb-0"><strong>{{ __('field_program') }}:</strong> {{ $row->studentEnroll->program->title ?? '' }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_fees_type') }}:</strong> {{ $row->category->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_session') }}:</strong> {{ $row->studentEnroll->session->title ?? '' }}</p>
                                    <p class="mb-0"><strong>{{ __('field_semester') }}:</strong> {{ $row->studentEnroll->semester->title ?? '' }} ({{ $row->studentEnroll->section->title ?? '' }})</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Summary Section -->
                    <div class="card mb-3 border-info">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-calculator"></i> {{ __('fee_summary') }}</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-3 text-center border-end">
                                    <small class="text-muted">{{ __('field_fee_amount') }}</small>
                                    <h5 class="mb-0">{{ number_format($row->fee_amount ?? 0, 2) }}</h5>
                                </div>
                                <div class="col-md-3 text-center border-end">
                                    <small class="text-muted">{{ __('field_discount') }}</small>
                                    <h5 class="mb-0 text-success">-{{ number_format($row->discount_amount ?? 0, 2) }}</h5>
                                </div>
                                <div class="col-md-3 text-center border-end">
                                    <small class="text-muted">{{ __('field_fine_amount') }}</small>
                                    <h5 class="mb-0 text-danger">+{{ number_format($row->fine_amount ?? 0, 2) }}</h5>
                                </div>
                                <div class="col-md-3 text-center">
                                    <small class="text-muted">{{ __('field_net_amount') }}</small>
                                    <h5 class="mb-0 text-primary"><strong>{{ number_format($row->total_amount ?? 0, 2) }}</strong></h5>
                                </div>
                            </div>
                            @if($row->status == 2 || $row->paid_amount > 0)
                            <hr class="my-2">
                            <div class="row">
                                <div class="col-md-6 text-center">
                                    <small class="text-muted">{{ __('field_already_paid') }}</small>
                                    {{-- Net, not gross. Where credit has been moved to
                                         another fee the two differ, and the gross figure
                                         read as a double payment: one fee showed
                                         "already paid 300,000" against a 150,000 charge
                                         with nothing outstanding. --}}
                                    <h5 class="mb-0 text-success">{{ number_format($row->net_paid_amount, 2) }}</h5>
                                    @if(abs(($row->paid_amount ?? 0) - $row->net_paid_amount) > 0.005)
                                        <small class="text-muted d-block">
                                            {{ __('of :gross received; :moved moved to another fee', [
                                                'gross' => number_format($row->paid_amount ?? 0, 2),
                                                'moved' => number_format(($row->paid_amount ?? 0) - $row->net_paid_amount, 2),
                                            ]) }}
                                        </small>
                                    @endif
                                </div>
                                <div class="col-md-6 text-center">
                                    <small class="text-muted">{{ __('field_remaining_balance') }}</small>
                                    <h5 class="mb-0 text-warning"><strong>{{ number_format(max(0, $row->remaining_balance ?? 0), 2) }}</strong></h5>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- ── Resit Course Details (shown only for resit fees) ── --}}
                    @if($row->resitRequest && $row->resitRequest->subject)
                    @php
                        $rr = $row->resitRequest;
                        $subject = $rr->subject;
                        $markingKey = $rr->student_enroll_id . '-' . $rr->subject_id;
                        $marking = isset($resitMarkings) ? ($resitMarkings[$markingKey] ?? null) : null;
                        $passingMarks = $subject->passing_marks ?? 50;

                        // ── Marks breakdown matching exam publishing logic ──
                        // Stored SubjectMarking fields
                        $storedAttendance = $marking ? (float)($marking->attendances ?? 0) : 0;
                        $storedAssignment = $marking ? (float)($marking->assignments ?? 0) : 0;
                        $storedActivity   = $marking ? (float)($marking->activities ?? 0) : 0;
                        $attendanceMarks  = round($storedAttendance, 2);

                        // Query Exam records and split by ExamType.is_final
                        $caExamMarks = 0;
                        $finalExamMarks = 0;
                        $rawCaMarks = 0;
                        $rawFinalMarks = 0;
                        $hasZeroContribution = false;

                        $allExams = \App\Models\Exam::where('student_enroll_id', $rr->student_enroll_id)
                            ->where('subject_id', $rr->subject_id)
                            ->with('type')
                            ->get();

                        foreach ($allExams as $exam) {
                            $examContribution = (float)$exam->contribution;
                            $isFinalType = $exam->type && $exam->type->is_final;

                            if ($exam->attendance == 1 && $exam->achieve_marks !== null) {
                                if ($isFinalType) {
                                    $rawFinalMarks += (float)$exam->achieve_marks;
                                } else {
                                    $rawCaMarks += (float)$exam->achieve_marks;
                                }

                                if ($examContribution <= 0) {
                                    $hasZeroContribution = true;
                                }

                                if ($examContribution > 0 && $exam->marks > 0) {
                                    $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                                    $contributedMarks = (($percentOfMarks / 100) * $examContribution);
                                    if ($isFinalType) {
                                        $finalExamMarks += $contributedMarks;
                                    } else {
                                        $caExamMarks += $contributedMarks;
                                    }
                                }
                            }
                        }

                        // Fallback when contribution weights are missing
                        $effectiveCaMarks = ($caExamMarks == 0 && $rawCaMarks > 0 && $hasZeroContribution)
                            ? $rawCaMarks : $caExamMarks;
                        $effectiveFinalMarks = ($finalExamMarks == 0 && $rawFinalMarks > 0 && $hasZeroContribution)
                            ? $rawFinalMarks : $finalExamMarks;

                        $totalCA    = round($attendanceMarks + $storedAssignment + $storedActivity + $effectiveCaMarks, 2);
                        $examMarksVal = round($effectiveFinalMarks, 2);
                        $totalMarks = round($totalCA + $examMarksVal, 2);

                        // Display values (matching exam publishing)
                        $attMarks   = round($attendanceMarks, 2);
                        $caMarksVal = round($totalCA - $attendanceMarks, 2);
                        $exMarks    = round($examMarksVal, 2);

                        $markPercent = ($totalMarks !== null && $subject->total_marks > 0) ? round(($totalMarks / $subject->total_marks) * 100, 1) : null;
                        $deficit = round($passingMarks - $totalMarks, 1);

                        // Grade lookup
                        $resitGrade = null;
                        if ($totalMarks !== null) {
                            $resitGrade = \App\Models\Grade::where('status', 1)
                                ->where('min_mark', '<=', $totalMarks)
                                ->where('max_mark', '>=', $totalMarks)
                                ->first();
                        }

                        $stateBadges = [
                            'requested'        => ['class' => 'badge-info',      'icon' => 'fa-paper-plane',    'label' => 'Requested'],
                            'awaiting_payment' => ['class' => 'badge-warning',   'icon' => 'fa-clock',          'label' => 'Awaiting Payment'],
                            'finance_review'   => ['class' => 'badge-primary',   'icon' => 'fa-search-dollar',  'label' => 'Finance Review'],
                            'approved'         => ['class' => 'badge-success',   'icon' => 'fa-check-circle',   'label' => 'Approved'],
                            'scheduled'        => ['class' => 'badge-dark',      'icon' => 'fa-calendar-check', 'label' => 'Scheduled'],
                        ];
                        $sb = $stateBadges[$rr->workflow_state] ?? ['class' => 'badge-secondary', 'icon' => 'fa-question', 'label' => ucfirst($rr->workflow_state ?? 'Unknown')];
                    @endphp
                    <div class="card border-danger mb-3">
                        <div class="card-header bg-danger text-white py-2">
                            <h6 class="mb-0"><i class="fas fa-redo-alt mr-1"></i> {{ __('Resit Course Details') }}</h6>
                        </div>
                        <div class="card-body py-3">
                            {{-- Course Info --}}
                            <div class="d-flex align-items-start mb-3">
                                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center mr-3" style="width:42px;height:42px;min-width:42px;">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 text-danger">{{ $subject->title ?? 'N/A' }}</h6>
                                    <span class="badge badge-dark mr-1">{{ $subject->code }}</span>
                                    <span class="badge badge-secondary">{{ $subject->credit_hour }} {{ __('Credit Hour(s)') }}</span>
                                    @if($subject->subject_type)
                                    <span class="badge badge-outline-info ml-1">{{ ucfirst($subject->subject_type) }}</span>
                                    @endif
                                </div>
                                <span class="badge {{ $sb['class'] }} px-2 py-1" style="font-size:0.8em;">
                                    <i class="fas {{ $sb['icon'] }} mr-1"></i> {{ $sb['label'] }}
                                </span>
                            </div>

                            {{-- Marks breakdown (matches exam publishing: Att | CA | EX | TOT | Grd) --}}
                            @if($marking)
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0 text-center" style="font-size:0.85em;">
                                    <thead style="background:#6c757d;color:#fff;">
                                        <tr>
                                            <th title="Attendance">Att</th>
                                            <th title="Continuous Assessment">CA</th>
                                            <th title="Final Exam">EX</th>
                                            <th title="Total">TOT</th>
                                            <th title="Grade">Grd</th>
                                            <th title="Pass Mark">Pass</th>
                                            <th title="Deficit">Deficit</th>
                                            <th title="Result">Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>{{ $attMarks ?? '-' }}</td>
                                            <td>{{ $caMarksVal ?? '-' }}</td>
                                            <td>{{ $exMarks ?? '-' }}</td>
                                            <td class="font-weight-bold text-danger">{{ $totalMarks ?? '-' }}/{{ $subject->total_marks ?? 100 }}</td>
                                            <td>
                                                @if($resitGrade)
                                                    <span class="badge {{ strtoupper($resitGrade->remark ?? '') === 'FAIL' || $resitGrade->point == 0 ? 'badge-danger' : 'badge-success' }}">{{ $resitGrade->title }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-success font-weight-bold">{{ $passingMarks }}</td>
                                            <td class="text-danger font-weight-bold">{{ $deficit !== null && $deficit > 0 ? '-' . $deficit : '0' }}</td>
                                            <td>
                                                @if($totalMarks !== null && $totalMarks >= $passingMarks)
                                                    <span class="badge badge-success">{{ __('Pass') }}</span>
                                                @else
                                                    <span class="badge badge-danger">{{ __('Fail') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mb-2" style="font-size:0.75em;">
                                <span class="text-muted"><strong>Att</strong> = Attendance | <strong>CA</strong> = Continuous Assessment | <strong>EX</strong> = Final Exam | <strong>TOT</strong> = Total | <strong>Grd</strong> = Grade</span>
                            </div>
                            @if($markPercent !== null)
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">{{ __('Score') }}: {{ $markPercent }}%</small>
                                    <small class="text-muted">{{ __('Pass') }}: {{ $passingMarks }}%</small>
                                </div>
                                <div class="progress" style="height:10px; position:relative;">
                                    <div class="progress-bar {{ $totalMarks >= $passingMarks ? 'bg-success' : 'bg-danger' }}" style="width:{{ min($markPercent, 100) }}%"></div>
                                    <div style="position:absolute;left:{{ $passingMarks }}%;top:-2px;bottom:-2px;width:2px;background:#333;z-index:2;" title="{{ __('Pass Mark') }}: {{ $passingMarks }}%"></div>
                                </div>
                            </div>
                            @endif
                            @endif

                            {{-- Enrollment context --}}
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <small class="text-muted"><i class="fas fa-calendar-alt mr-1"></i>{{ __('Original Session') }}:</small>
                                    <strong>{{ $rr->session->title ?? '-' }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted"><i class="fas fa-hashtag mr-1"></i>{{ __('Request') }} #:</small>
                                    <strong>{{ $rr->id }}</strong>
                                    <small class="text-muted ml-2">{{ $rr->created_at ? $rr->created_at->format('M d, Y') : '' }}</small>
                                </div>
                            </div>
                            @if($rr->resitSession || $rr->resitSemester)
                            <div class="mt-1">
                                <small class="text-muted"><i class="fas fa-redo mr-1"></i>{{ __('Resit Scheduled') }}:</small>
                                <strong>{{ $rr->resitSession->title ?? '' }} @if($rr->resitSemester) / {{ $rr->resitSemester->title }} @endif</strong>
                            </div>
                            @endif

                            <div class="alert alert-danger mb-0 mt-2 py-2" style="font-size:0.85em;">
                                <i class="fas fa-ban mr-1"></i>
                                <strong>{{ __('Partial payment is not allowed for resit fees.') }}</strong>
                                {{ __('The full remaining balance must be paid in one transaction.') }}
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- More than this instalment needs.

                         Students here pay toward the year, not toward an
                         instalment, so this is the ordinary case. Rather than
                         booking the excess as credit for somebody to apply by
                         hand later, it is carried onto the student's other
                         instalments - and the split is shown, and can be
                         changed, before anything is recorded. --}}
                    <div class="alert alert-warning d-none" id="overpayment-warning-{{ $row->id }}">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> {{ __('overpayment_warning') }}</h6>
                        <p class="mb-1">{{ __('overpayment_message') }}</p>
                        <hr class="my-2">
                        <div class="mb-2">
                            <strong>{{ __('overpayment_amount') }}:</strong>
                            <span id="overpayment-amount-{{ $row->id }}" class="text-danger font-weight-bold">0.00</span> {!! $setting->currency_symbol !!}
                        </div>

                        <div id="split-loading-{{ $row->id }}" class="text-muted small d-none">
                            <i class="fas fa-spinner fa-spin"></i> {{ __('Working out how this is spread...') }}
                        </div>

                        <table class="table table-sm mb-2 bg-white d-none" id="split-table-{{ $row->id }}">
                            <thead>
                                <tr>
                                    <th>{{ __('field_fees_type') }}</th>
                                    <th>{{ __('field_due_date') }}</th>
                                    <th class="text-right">{{ __('field_remaining_balance') }}</th>
                                    <th class="text-right" style="width:170px">{{ __('field_amount_to_pay') }}</th>
                                </tr>
                            </thead>
                            <tbody id="split-rows-{{ $row->id }}"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">{{ __('field_total') }}</th>
                                    <th class="text-right" id="split-total-{{ $row->id }}">0.00</th>
                                </tr>
                            </tfoot>
                        </table>

                        <div id="split-mismatch-{{ $row->id }}" class="alert alert-danger py-2 small mb-2 d-none"></div>

                        {{-- Money beyond every instalment the student owes. It
                             still becomes credit, as it always did, but never by
                             accident. --}}
                        <div class="alert alert-danger mb-0 d-none" id="leftover-{{ $row->id }}">
                            <strong>
                                <i class="fas fa-exclamation-circle"></i>
                                <span id="leftover-amount-{{ $row->id }}">0.00</span> {!! $setting->currency_symbol !!}
                                {{ __('is more than every instalment this student owes.') }}
                            </strong>
                            <p class="mb-2 mt-1 small">{{ __('overpayment_credit_note') }}</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       id="allow_leftover_credit-{{ $row->id }}"
                                       name="allow_leftover_credit" value="1">
                                <label class="form-check-label" for="allow_leftover_credit-{{ $row->id }}">
                                    {{ __('I have checked this with the student. Hold the remainder as credit.') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="fee_id" value="{{ $row->id }}">
                    <input type="hidden" name="allow_overpayment" id="allow_overpayment-{{ $row->id }}" value="0">

                    <!-- Payment Form -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="due_date" class="form-label">{{ __('field_due_date') }}</label>
                            <input type="date" class="form-control" name="due_date" value="{{ $row->due_date }}" readonly>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="pay_date" class="form-label">{{ __('field_pay_date') }} <span>*</span></label>
                            <input type="date" class="form-control" name="pay_date" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="payment_amount" class="form-label">{{ __('field_amount_to_pay') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                            @if($row->resitRequest)
                            <input type="number" step="0.01" class="form-control bg-light" name="paid_amount" id="payment_amount-{{ $row->id }}" value="{{ round(max(0, $row->remaining_balance ?? 0), 2) }}" min="{{ round(max(0, $row->remaining_balance ?? 0), 2) }}" max="{{ round(max(0, $row->remaining_balance ?? 0), 2) }}" readonly required>
                            <small class="form-text text-danger">
                                <i class="fas fa-lock mr-1"></i>{{ __('Full payment required for resit fees.') }}
                            </small>
                            @else
                            <input type="number" step="0.01" min="0.01" class="form-control" name="paid_amount" id="payment_amount-{{ $row->id }}" value="{{ round(max(0, $row->remaining_balance ?? 0), 2) }}" required>
                            <small class="form-text text-muted" id="payment-hint-{{ $row->id }}">
                                {{ __('balance_due') }}: {{ number_format(max(0, $row->remaining_balance ?? 0), 2) }} {!! $setting->currency_symbol !!}
                            </small>
                            @endif
                        </div>

                        <div class="form-group col-md-6">
                            <label for="payment_method" class="form-label">{{ __('field_payment_method') }} <span>*</span></label>
                            <select class="form-control" name="payment_method" id="payment_method-{{ $row->id }}" required>
                                <option value="">{{ __('select') }}</option>
                                <option value="1">{{ __('payment_method_card') }}</option>
                                <option value="2">{{ __('payment_method_cash') }}</option>
                                <option value="3">{{ __('payment_method_cheque') }}</option>
                                <option value="4">{{ __('payment_method_bank') }}</option>
                                <option value="5">{{ __('payment_method_e_wallet') }}</option>
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="payment_account_id" class="form-label">{{ __('field_payment_account') }}</label>
                            <select class="form-control" name="payment_account_id" id="payment_account_id-{{ $row->id }}">
                                <option value="">{{ __('select_payment_account_optional') }}</option>
                                @foreach(\App\Models\PaymentAccount::where('status', 1)->orderBy('title')->get() as $account)
                                    <option value="{{ $account->id }}">
                                        {{ $account->title }} - {{ __('field_balance') }}: {!! $setting->currency_symbol !!}{{ number_format($account->current_balance, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="note" class="form-label">{{ __('field_note') }}</label>
                            <input type="text" class="form-control" name="note" id="note-{{ $row->id }}" placeholder="{{ __('optional_note') }}">
                        </div>
                    </div>
                </div>
                {{-- Read the figures back before any money is recorded.

                     Recording a receipt posts to the ledger, credits the payment
                     account and issues a receipt number. It can be reversed, but
                     a reversal is itself a permanent entry, so an amount typed
                     wrongly is a correction on the record rather than a quiet
                     edit. The officer confirms the number against what was
                     actually handed over. --}}
                <div class="modal-body d-none" id="pay-confirm-{{ $row->id }}">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading mb-1">
                            <i class="fas fa-hand-holding-usd"></i> {{ __('Check this against the money you received') }}
                        </h6>
                        <p class="mb-0">{{ __('This records the amount below as received from the student. Reversing it later leaves a correction on the record, so confirm the figure now.') }}</p>
                    </div>

                    <div class="text-center my-3">
                        <div class="text-muted small text-uppercase">{{ __('Amount to record as received') }}</div>
                        <div style="font-size:2.4rem;line-height:1.1;font-weight:700;" id="pay-confirm-amount-{{ $row->id }}">0.00</div>
                        <div class="text-muted">{!! $setting->currency_symbol !!}</div>
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="fw-normal text-muted w-50">{{ __('field_name') }}</th>
                                <td class="fw-bold">{{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_matricule') }}</th>
                                <td class="fw-bold">#{{ $row->studentEnroll->matricule ?? ($row->studentEnroll->student->student_id ?? '') }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_fees_type') }}</th>
                                <td class="fw-bold">{{ $row->category->title ?? '' }}</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_payment_method') }}</th>
                                <td class="fw-bold" id="pay-confirm-method-{{ $row->id }}">&mdash;</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_payment_account') }}</th>
                                <td id="pay-confirm-account-{{ $row->id }}">&mdash;</td>
                            </tr>
                            <tr>
                                <th class="fw-normal text-muted">{{ __('field_remaining_balance') }} {{ __('after this') }}</th>
                                <td class="fw-bold" id="pay-confirm-balance-{{ $row->id }}">&mdash;</td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="pay-confirm-notes-{{ $row->id }}"></div>

                    {{-- A deliberate act, not a second reflex click on the same
                         spot: the confirm button stays disabled until this is
                         ticked. --}}
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="pay-confirm-check-{{ $row->id }}">
                        <label class="form-check-label" for="pay-confirm-check-{{ $row->id }}">
                            {{ __('I have counted this and it matches the amount the student paid.') }}
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="pay-close-{{ $row->id }}"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="button" class="btn btn-secondary d-none" id="pay-back-{{ $row->id }}"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</button>
                    {{-- type="button": nothing is recorded until the figures have
                         been read back. --}}
                    <button type="button" class="btn btn-success" id="submit-btn-{{ $row->id }}"><i class="fas fa-money-check"></i> {{ __('btn_received') }}</button>
                    {{-- Disabled while hidden, so Enter in a field cannot submit
                         the form past the confirmation. --}}
                    <button type="submit" class="btn btn-success d-none" id="pay-confirm-btn-{{ $row->id }}" disabled>
                        <i class="fas fa-check"></i> {{ __('Yes, record this payment') }}
                    </button>
                </div>
              </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const feeId = {{ $row->id }};
        const paymentAmountField = document.getElementById('payment_amount-' + feeId);
        const overpaymentWarning = document.getElementById('overpayment-warning-' + feeId);
        const overpaymentAmountSpan = document.getElementById('overpayment-amount-' + feeId);
        const allowOverpaymentField = document.getElementById('allow_overpayment-' + feeId);
        const submitBtn = document.getElementById('submit-btn-' + feeId);
        const maxAmount = {{ round(max(0, $row->remaining_balance ?? 0), 2) }};
        const SPLIT_URL      = {!! json_encode(url('admin/fees-student-split')) !!};
        const SPLIT_TOO_MUCH = {!! json_encode(__('The split comes to more than the amount received.')) !!};
        const SPLIT_FAILED   = {!! json_encode(__('Could not work out how this payment is spread. Close and try again.')) !!};
        const THIS_FEE       = {!! json_encode(__('this fee')) !!};
        const CONFIRM_LABEL  = {!! json_encode(__('btn_confirm_overpayment')) !!};
        const RECEIVED_LABEL = {!! json_encode(__('btn_received')) !!};
        
        // --- Spreading the payment across the student's instalments ---------
        //
        // How the money divides is NOT worked out here. The browser asks the
        // server, which runs the one class that knows the rule, so what the
        // bursar is shown is what the money will actually move against. A second
        // copy of this arithmetic in JavaScript is exactly how the modal and the
        // till end up disagreeing.
        const splitTable    = document.getElementById('split-table-' + feeId);
        const splitRows     = document.getElementById('split-rows-' + feeId);
        const splitTotal    = document.getElementById('split-total-' + feeId);
        const splitLoading  = document.getElementById('split-loading-' + feeId);
        const splitMismatch = document.getElementById('split-mismatch-' + feeId);
        const leftoverBox   = document.getElementById('leftover-' + feeId);
        const leftoverSpan  = document.getElementById('leftover-amount-' + feeId);
        const leftoverTick  = document.getElementById('allow_leftover_credit-' + feeId);

        let splitTimer = null;

        const asMoney = function (value) {
            return (parseFloat(value) || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        };

        function totalAllocated() {
            let sum = 0;
            splitRows.querySelectorAll('.split-amount').forEach(function (input) {
                sum += parseFloat(input.value) || 0;
            });
            return Math.round(sum * 100) / 100;
        }

        // The bursar may move money between the instalments. Whatever they do,
        // the figures have to agree before the form may be sent.
        function refreshSplitTotals() {
            const entered = parseFloat(paymentAmountField.value) || 0;
            const allocated = totalAllocated();
            const leftover = Math.round((entered - allocated) * 100) / 100;

            splitTotal.textContent = asMoney(allocated);

            if (allocated - entered > 0.005) {
                splitMismatch.classList.remove('d-none');
                splitMismatch.textContent = SPLIT_TOO_MUCH
                    + ' (' + asMoney(allocated) + ' / ' + asMoney(entered) + ')';
            } else {
                splitMismatch.classList.add('d-none');
            }

            if (leftover > 0.005) {
                leftoverBox.classList.remove('d-none');
                leftoverSpan.textContent = asMoney(leftover);
            } else {
                leftoverBox.classList.add('d-none');
                if (leftoverTick) { leftoverTick.checked = false; }
            }
        }

        function renderSplit(data) {
            const legs = data.legs || [];
            splitRows.innerHTML = '';

            legs.forEach(function (leg, index) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + (leg.category || '') + (leg.is_origin
                        ? ' <span class="badge badge-info">' + THIS_FEE + '</span>'
                        : '') + '</td>'
                    + '<td>' + (leg.due_date || '&mdash;') + '</td>'
                    + '<td class="text-right">' + asMoney(leg.outstanding) + '</td>'
                    + '<td class="text-right">'
                    + '<input type="hidden" name="allocation[' + index + '][fee_id]" value="' + leg.fee_id + '">'
                    + '<input type="number" step="0.01" min="0" max="' + leg.outstanding + '"'
                    + ' class="form-control form-control-sm text-right split-amount"'
                    + ' name="allocation[' + index + '][applied]" value="' + Number(leg.applied).toFixed(2) + '">'
                    + '</td>';
                splitRows.appendChild(tr);
            });

            splitRows.querySelectorAll('.split-amount').forEach(function (input) {
                input.addEventListener('input', refreshSplitTotals);
            });

            splitTable.classList.toggle('d-none', legs.length < 2);
            refreshSplitTotals();
        }

        function askForSplit() {
            const entered = parseFloat(paymentAmountField.value) || 0;

            clearTimeout(splitTimer);
            splitTimer = setTimeout(function () {
                splitLoading.classList.remove('d-none');

                fetch(SPLIT_URL + '/' + feeId + '?amount=' + entered,
                      { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        splitLoading.classList.add('d-none');
                        // The bursar may have typed on while this was in flight.
                        if (Math.abs((parseFloat(paymentAmountField.value) || 0) - entered) > 0.005) { return; }
                        renderSplit(data);
                    })
                    .catch(function () {
                        splitLoading.classList.add('d-none');
                        splitMismatch.classList.remove('d-none');
                        splitMismatch.textContent = SPLIT_FAILED;
                    });
            }, 300);
        }

        function clearSplit() {
            splitRows.innerHTML = '';
            splitTable.classList.add('d-none');
            splitMismatch.classList.add('d-none');
            leftoverBox.classList.add('d-none');
            if (leftoverTick) { leftoverTick.checked = false; }
        }

        if(paymentAmountField) {
            paymentAmountField.addEventListener('input', function() {
                let paymentAmount = parseFloat(this.value) || 0;

                if(paymentAmount > maxAmount) {
                    // More than this instalment needs: show where the rest goes.
                    let overpaymentAmount = paymentAmount - maxAmount;
                    overpaymentWarning.classList.remove('d-none');
                    overpaymentAmountSpan.textContent = overpaymentAmount.toFixed(2);
                    allowOverpaymentField.value = '1';
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + CONFIRM_LABEL;
                    submitBtn.classList.remove('btn-success');
                    submitBtn.classList.add('btn-warning');
                    askForSplit();
                } else {
                    // Within this fee's balance: nothing to spread.
                    overpaymentWarning.classList.add('d-none');
                    overpaymentAmountSpan.textContent = '0.00';
                    allowOverpaymentField.value = '0';
                    clearSplit();
                    submitBtn.innerHTML = '<i class="fas fa-money-check"></i> ' + RECEIVED_LABEL;
                    submitBtn.classList.remove('btn-warning');
                    submitBtn.classList.add('btn-success');
                }
            });
        }

        // --- The confirmation step -------------------------------------------
        const form        = submitBtn ? submitBtn.closest('form') : null;
        const formBody    = form ? form.querySelector('.modal-body') : null;
        const confirmStep = document.getElementById('pay-confirm-' + feeId);
        const confirmBtn  = document.getElementById('pay-confirm-btn-' + feeId);
        const backBtn     = document.getElementById('pay-back-' + feeId);
        const closeBtn    = document.getElementById('pay-close-' + feeId);
        const confirmTick = document.getElementById('pay-confirm-check-' + feeId);

        if (!form || !confirmStep || !confirmBtn) { return; }

        const money = function (value) {
            return (parseFloat(value) || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        };

        const escapeHtml = function (value) {
            const holder = document.createElement('div');
            holder.textContent = value === null || value === undefined ? '' : value;
            return holder.innerHTML;
        };

        const selectedText = function (id) {
            const field = document.getElementById(id);
            if (!field || field.selectedIndex < 0) { return ''; }
            const option = field.options[field.selectedIndex];
            return option && option.value !== '' ? option.text.trim() : '';
        };

        const fill = function () {
            const amount = parseFloat(paymentAmountField ? paymentAmountField.value : 0) || 0;

            document.getElementById('pay-confirm-amount-' + feeId).textContent = money(amount);

            const method = selectedText('payment_method-' + feeId);
            document.getElementById('pay-confirm-method-' + feeId).textContent =
                method || {!! json_encode(__('select')) !!};

            const account = selectedText('payment_account_id-' + feeId);
            document.getElementById('pay-confirm-account-' + feeId).innerHTML = account
                ? escapeHtml(account)
                : '<span class="text-muted">' + {!! json_encode(__('select_payment_account_optional')) !!} + '</span>';

            const after = maxAmount - amount;
            document.getElementById('pay-confirm-balance-' + feeId).textContent =
                money(Math.max(0, after)) + ' {!! $setting->currency_symbol !!}';

            // Anything the officer should look at twice is said here rather than
            // left for them to work out from the numbers.
            const notes = [];

            if (amount > maxAmount + 0.005) {
                notes.push({
                    level: 'danger',
                    text: {!! json_encode(__('This is more than the balance due. The extra is held as credit for this student.')) !!}
                        + ' (' + money(amount - maxAmount) + ' {!! $setting->currency_symbol !!})'
                });
            } else if (amount > 0 && amount < maxAmount - 0.005) {
                notes.push({
                    level: 'info',
                    text: {!! json_encode(__('This is a part payment. The rest stays outstanding.')) !!}
                });
            }

            if (amount <= 0) {
                notes.push({
                    level: 'danger',
                    text: {!! json_encode(__('The amount is zero. Nothing would be recorded as received.')) !!}
                });
            }

            document.getElementById('pay-confirm-notes-' + feeId).innerHTML = notes.map(function (note) {
                return '<div class="alert alert-' + note.level + ' py-2 mb-0 mt-2 small">'
                     + escapeHtml(note.text) + '</div>';
            }).join('');
        };

        const showConfirm = function () {
            fill();
            if (formBody) { formBody.classList.add('d-none'); }
            confirmStep.classList.remove('d-none');
            submitBtn.classList.add('d-none');
            if (closeBtn) { closeBtn.classList.add('d-none'); }
            if (backBtn) { backBtn.classList.remove('d-none'); }
            confirmBtn.classList.remove('d-none');
            // Ticked afresh every time: a confirmation carried over from the
            // previous look is not a confirmation.
            if (confirmTick) { confirmTick.checked = false; }
            confirmBtn.disabled = true;
        };

        const showForm = function () {
            if (formBody) { formBody.classList.remove('d-none'); }
            confirmStep.classList.add('d-none');
            // Going back withdraws the confirmation, so the tick goes with it.
            if (confirmTick) { confirmTick.checked = false; }
            submitBtn.classList.remove('d-none');
            if (closeBtn) { closeBtn.classList.remove('d-none'); }
            if (backBtn) { backBtn.classList.add('d-none'); }
            confirmBtn.classList.add('d-none');
            confirmBtn.disabled = true;
        };

        submitBtn.addEventListener('click', function () {
            // The required fields are still enforced first: there is no point
            // confirming figures the server is going to refuse.
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                form.reportValidity();
                return;
            }
            showConfirm();
        });

        if (backBtn) { backBtn.addEventListener('click', showForm); }

        if (confirmTick) {
            confirmTick.addEventListener('change', function () {
                confirmBtn.disabled = !this.checked;
            });
        }

        // Recording takes a moment, and a second press would take the money twice.
        form.addEventListener('submit', function () {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> '
                + {!! json_encode(__('Recording...')) !!};
        });

        // Reopening always starts at the form, never on a stale confirmation.
        const payModal = document.getElementById('payModal-' + feeId);
        if (payModal) { payModal.addEventListener('hidden.bs.modal', showForm); }
    });
    </script>