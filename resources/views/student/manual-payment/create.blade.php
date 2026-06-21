@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('upload_payment_receipt') }}</h5>
                    </div>
                    <div class="card-block">
                        @php $isResitFee = $fee->resitRequest !== null; @endphp
                        <!-- Fee Details -->
                        <div class="alert alert-info">
                            <h6 class="mb-2"><strong>{{ __('fee_details') }}</strong></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_fees_type') }}:</strong> {{ $fee->category->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_session') }}:</strong> {{ $fee->studentEnroll->session->title ?? '' }}</p>
                                    <p class="mb-1"><strong>{{ __('field_semester') }}:</strong> {{ $fee->studentEnroll->semester->title ?? '' }}</p>
                                    @if($fee->studentEnroll->program)
                                    <p class="mb-1"><strong>{{ __('field_program') }}:</strong> {{ $fee->studentEnroll->program->title ?? '' }}</p>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>{{ __('field_fee') }}:</strong> 
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$fee->fee_amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$fee->fee_amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_discount') }}:</strong> 
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$fee->discount_amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$fee->discount_amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_fine_amount') }}:</strong> 
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$fee->fine_amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$fee->fine_amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_net_amount') }}:</strong> 
                                        @php
                                            $net_amount = $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
                                        @endphp
                                        <span class="text-primary font-weight-bold">
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$net_amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$net_amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_paid_amount') }}:</strong> 
                                        <span class="text-success">
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$fee->paid_amount, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$fee->paid_amount, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>{{ __('field_remaining_balance') }}:</strong> 
                                        <span class="text-danger font-weight-bold h5">
                                        @if(isset($setting->decimal_place))
                                        {{ number_format((float)$fee->remaining_balance, $setting->decimal_place, '.', '') }} 
                                        @else
                                        {{ number_format((float)$fee->remaining_balance, 2, '.', '') }} 
                                        @endif 
                                        {!! $setting->currency_symbol !!}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            @if($fee->due_date)
                            <div class="mt-2">
                                @php $isDueOrPast = \Carbon\Carbon::parse($fee->due_date)->isPast(); @endphp
                                <p class="mb-0"><strong>{{ __('field_due_date') }}:</strong> 
                                    <span class="{{ $isDueOrPast ? 'text-danger font-weight-bold' : '' }}">
                                        {{ \Carbon\Carbon::parse($fee->due_date)->format('l, F j, Y') }}
                                        @if($isDueOrPast)
                                        <i class="fas fa-exclamation-triangle"></i> {{ __('Overdue') }}
                                        @endif
                                    </span>
                                </p>
                            </div>
                            @endif
                            @if($isResitFee)
                            <div class="alert alert-danger mb-0 mt-2">
                                <i class="fas fa-ban"></i> <strong>{{ __('Partial payment is not allowed for resit fees.') }}</strong>
                                <br><small>{{ __('You must pay the full remaining balance to proceed with your resit examination.') }}</small>
                            </div>
                            @else
                            <div class="alert alert-warning mb-0 mt-2">
                                <i class="fas fa-info-circle"></i> <strong>{{ __('msg_partial_payment_allowed') }}</strong>
                                <br><small>{{ __('msg_remaining_balance_notice') }}</small>
                            </div>
                            @endif
                        </div>

                        {{-- ── Resit Course Details (shown only for resit fees) ── --}}
                        @if($fee->resitRequest && $fee->resitRequest->subject)
                        @php
                            $rr = $fee->resitRequest;
                            $subject = $rr->subject;
                            $marking = $resitMarking;
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

                            // Workflow state badges
                            $stateBadges = [
                                'requested'        => ['class' => 'badge-info',      'icon' => 'fa-paper-plane',    'label' => 'Requested'],
                                'awaiting_payment' => ['class' => 'badge-warning',   'icon' => 'fa-clock',          'label' => 'Awaiting Payment'],
                                'finance_review'   => ['class' => 'badge-primary',   'icon' => 'fa-search-dollar',  'label' => 'Finance Review'],
                                'approved'         => ['class' => 'badge-success',   'icon' => 'fa-check-circle',   'label' => 'Approved'],
                                'scheduled'        => ['class' => 'badge-dark',      'icon' => 'fa-calendar-check', 'label' => 'Scheduled'],
                            ];
                            $sb = $stateBadges[$rr->workflow_state] ?? ['class' => 'badge-secondary', 'icon' => 'fa-question', 'label' => ucfirst($rr->workflow_state)];
                        @endphp
                        <div class="card border-danger mb-3">
                            <div class="card-header bg-danger text-white py-2">
                                <h6 class="mb-0"><i class="fas fa-redo-alt mr-1"></i> {{ __('Resit Course Details') }}</h6>
                            </div>
                            <div class="card-body py-3">
                                {{-- Course Info Row --}}
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start">
                                            <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center mr-3" style="width:48px;height:48px;min-width:48px;">
                                                <i class="fas fa-book fa-lg"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1 text-danger">{{ $subject->title ?? $subject->subject_name ?? 'N/A' }}</h5>
                                                <span class="badge badge-dark mr-1">{{ $subject->code }}</span>
                                                <span class="badge badge-secondary">{{ $subject->credit_hour }} {{ __('Credit Hour(s)') }}</span>
                                                @if($subject->subject_type)
                                                <span class="badge badge-outline-info ml-1">{{ ucfirst($subject->subject_type) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-right mt-2 mt-md-0">
                                        <span class="badge {{ $sb['class'] }} px-3 py-2" style="font-size:0.85em;">
                                            <i class="fas {{ $sb['icon'] }} mr-1"></i> {{ $sb['label'] }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Marks & Performance (matches exam publishing: Att | CA | EX | TOT | Grd) --}}
                                @if($marking)
                                <div class="card border-light mb-3">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="mb-0 text-muted"><i class="fas fa-chart-bar mr-1"></i> {{ __('Your Performance') }}</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 text-center" style="font-size:0.9em;">
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
                                                        <td class="font-weight-bold text-danger" style="font-size:1.1em;">{{ $totalMarks ?? '-' }}/{{ $subject->total_marks ?? 100 }}</td>
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
                                        <div class="mt-1" style="font-size:0.75em;">
                                            <span class="text-muted"><strong>Att</strong> = Attendance | <strong>CA</strong> = Continuous Assessment | <strong>EX</strong> = Final Exam | <strong>TOT</strong> = Total | <strong>Grd</strong> = Grade</span>
                                        </div>

                                        {{-- Visual progress bar --}}
                                        @if($markPercent !== null)
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">{{ __('Score') }}: {{ $markPercent }}%</small>
                                                <small class="text-muted">{{ __('Pass') }}: {{ $passingMarks }}%</small>
                                            </div>
                                            <div class="progress" style="height:12px; position:relative;">
                                                <div class="progress-bar {{ $totalMarks >= $passingMarks ? 'bg-success' : 'bg-danger' }}" role="progressbar" style="width:{{ min($markPercent, 100) }}%" aria-valuenow="{{ $markPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                {{-- Pass threshold marker --}}
                                                <div style="position:absolute;left:{{ $passingMarks }}%;top:-2px;bottom:-2px;width:2px;background:#333;z-index:2;" title="{{ __('Pass Mark') }}: {{ $passingMarks }}%"></div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                {{-- Enrollment & Request Context --}}
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <td class="text-muted py-1" style="width:140px;"><i class="fas fa-calendar-alt mr-1"></i> {{ __('Original Session') }}</td>
                                                <td class="py-1 font-weight-bold">{{ $rr->session->title ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted py-1"><i class="fas fa-layer-group mr-1"></i> {{ __('Semester') }}</td>
                                                <td class="py-1 font-weight-bold">{{ $fee->studentEnroll->semester->title ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted py-1"><i class="fas fa-graduation-cap mr-1"></i> {{ __('field_program') }}</td>
                                                <td class="py-1 font-weight-bold">{{ $fee->studentEnroll->program->title ?? '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <td class="text-muted py-1" style="width:140px;"><i class="fas fa-hashtag mr-1"></i> {{ __('Request') }} #</td>
                                                <td class="py-1 font-weight-bold">{{ $rr->id }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted py-1"><i class="fas fa-clock mr-1"></i> {{ __('Requested On') }}</td>
                                                <td class="py-1 font-weight-bold">{{ $rr->created_at ? $rr->created_at->format('M d, Y h:i A') : '-' }}</td>
                                            </tr>
                                            @if($rr->resitSession || $rr->resitSemester)
                                            <tr>
                                                <td class="text-muted py-1"><i class="fas fa-redo mr-1"></i> {{ __('Resit Scheduled') }}</td>
                                                <td class="py-1 font-weight-bold">
                                                    {{ $rr->resitSession->title ?? '' }}
                                                    @if($rr->resitSemester) / {{ $rr->resitSemester->title }} @endif
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </div>
                                </div>

                                {{-- Important notice --}}
                                <div class="alert alert-danger mb-0 mt-2 py-2">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-triangle mt-1 mr-2"></i>
                                        <div>
                                            <strong>{{ __('Important') }}:</strong>
                                            {{ __('This fee is for a resit examination. Payment must be completed before you can sit for the resit exam. Once payment is verified, your resit request will be approved automatically.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Upload Form -->
                        <form class="needs-validation" novalidate method="post" action="{{ route($route.'.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="fee_id" value="{{ $fee->id }}">

                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label for="receipt_file">{{ __('field_receipt_file') }} <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="receipt_file" id="receipt_file" accept="image/jpeg,image/png,application/pdf" required>
                                    <small class="form-text text-muted">
                                        {{ __('msg_allowed_file_types') }}: JPEG, PNG, PDF ({{ __('msg_max_file_size') }}: 5MB)
                                    </small>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_receipt_file') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="payment_reference">{{ __('field_payment_reference') }}</label>
                                    <input type="text" class="form-control" name="payment_reference" id="payment_reference" value="{{ old('payment_reference') }}" placeholder="{{ __('placeholder_transaction_id') }}">
                                    <small class="form-text text-muted">{{ __('msg_payment_reference_help') }}</small>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="payment_date">{{ __('field_payment_date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_payment_date') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="amount">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span class="text-danger">*</span></label>
                                    @if($isResitFee)
                                    <input type="number" step="0.01" class="form-control bg-light" name="amount" id="amount" value="{{ $fee->remaining_balance }}" min="{{ $fee->remaining_balance }}" max="{{ $fee->remaining_balance }}" readonly required>
                                    <small class="form-text text-danger">
                                        <i class="fas fa-lock mr-1"></i>{{ __('Full payment required for resit fees.') }} {{ number_format($fee->remaining_balance, 2) }} {!! $setting->currency_symbol !!}
                                    </small>
                                    @else
                                    <input type="number" step="0.01" class="form-control" name="amount" id="amount" value="{{ old('amount', $fee->remaining_balance) }}" min="0.01" max="{{ $fee->remaining_balance }}" required>
                                    <small class="form-text text-muted">
                                        {{ __('msg_partial_payment_allowed') }} Max: {{ number_format($fee->remaining_balance, 2) }} {!! $setting->currency_symbol !!}
                                    </small>
                                    @endif
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_amount') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="payment_method">{{ __('field_payment_method') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="payment_method" id="payment_method" required>
                                        <option value="">{{ __('select') }}</option>
                                        <option value="1" {{ old('payment_method') == 1 ? 'selected' : '' }}>{{ __('payment_method_card') }}</option>
                                        <option value="2" {{ old('payment_method') == 2 ? 'selected' : '' }}>{{ __('payment_method_cash') }}</option>
                                        <option value="3" {{ old('payment_method') == 3 ? 'selected' : '' }}>{{ __('payment_method_cheque') }}</option>
                                        <option value="4" {{ old('payment_method') == 4 ? 'selected' : '' }}>{{ __('payment_method_bank') }}</option>
                                        <option value="5" {{ old('payment_method') == 5 ? 'selected' : '' }}>{{ __('payment_method_e_wallet') }}</option>
                                        <option value="6" {{ old('payment_method') == 6 ? 'selected' : '' }}>{{ __('payment_method_manual') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_payment_method') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-12">
                                    <label for="student_note">{{ __('field_note') }}</label>
                                    <textarea class="form-control" name="student_note" id="student_note" rows="3" placeholder="{{ __('placeholder_payment_note') }}">{{ old('student_note') }}</textarea>
                                    <small class="form-text text-muted">{{ __('msg_payment_note_help') }}</small>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i> {{ __('btn_submit_receipt') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('scripts')
<script>
    // Preview uploaded file
    document.getElementById('receipt_file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const fileSize = file.size / 1024 / 1024; // in MB
            if (fileSize > 5) {
                alert('{{ __("msg_file_too_large") }}');
                e.target.value = '';
            }
        }
    });
</script>
@endsection
