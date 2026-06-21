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
                                    <h5 class="mb-0 text-success">{{ number_format($row->paid_amount ?? 0, 2) }}</h5>
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

                    <!-- Overpayment Warning (hidden by default) -->
                    <div class="alert alert-warning d-none" id="overpayment-warning-{{ $row->id }}">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> {{ __('overpayment_warning') }}</h6>
                        <p class="mb-1">{{ __('overpayment_message') }}</p>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>{{ __('overpayment_amount') }}:</strong> 
                                <span id="overpayment-amount-{{ $row->id }}" class="text-danger font-weight-bold">0.00</span> {!! $setting->currency_symbol !!}
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">{{ __('overpayment_credit_note') }}</small>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success" id="submit-btn-{{ $row->id }}"><i class="fas fa-money-check"></i> {{ __('btn_received') }}</button>
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
        
        if(paymentAmountField) {
            paymentAmountField.addEventListener('input', function() {
                let paymentAmount = parseFloat(this.value) || 0;
                
                if(paymentAmount > maxAmount) {
                    // Show overpayment warning
                    let overpaymentAmount = paymentAmount - maxAmount;
                    overpaymentWarning.classList.remove('d-none');
                    overpaymentAmountSpan.textContent = overpaymentAmount.toFixed(2);
                    allowOverpaymentField.value = '1';
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> {{ __("btn_confirm_overpayment") }}';
                    submitBtn.classList.remove('btn-success');
                    submitBtn.classList.add('btn-warning');
                } else {
                    // Hide overpayment warning
                    overpaymentWarning.classList.add('d-none');
                    overpaymentAmountSpan.textContent = '0.00';
                    allowOverpaymentField.value = '0';
                    submitBtn.innerHTML = '<i class="fas fa-money-check"></i> {{ __("btn_received") }}';
                    submitBtn.classList.remove('btn-warning');
                    submitBtn.classList.add('btn-success');
                }
            });
        }
    });
    </script>