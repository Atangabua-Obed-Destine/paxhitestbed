@extends('admin.layouts.master')
@section('title', __('Resit Requests'))
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('Resit Requests') }}</h5>
                        <span class="badge bg-primary">{{ $requests->total() }}</span>
                    </div>
                    <div class="card-block">
                        <!-- AUTOMATED WORKFLOW NOTICE -->
                        <div class="alert alert-success mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-robot fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-1 text-white"><strong>{{ __('Automated Resit Workflow') }}</strong></h6>
                                    <p class="mb-0" style="font-size: 13px; opacity: 0.95;">
                                        {{ __('This system now operates fully automatically. When finance verifies payment, resits are automatically scheduled to the next available resit semester, and students are automatically enrolled. This page is for viewing and monitoring only.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <strong>{{ __('How it works:') }}</strong>
                            <ol class="mb-0 mt-2" style="padding-left: 20px;">
                                <li>{{ __('Student requests resit for failed course → Fee automatically assigned') }}</li>
                                <li>{{ __('Finance verifies payment → Resit automatically scheduled to next resit semester') }}</li>
                                <li>{{ __('When all failed courses resolved (scheduled or declined) → Student automatically progressed to resit semester') }}</li>
                                <li>{{ __('After resit semester → Normal progression rules apply') }}</li>
                            </ol>
                        </div>
                        
                        <form method="get" action="{{ route('admin.resit-requests.index') }}" class="row g-2 align-items-end">
                            <div class="form-group col-md-3">
                                <label for="filter_session" class="form-label">{{ __('Session') }}</label>
                                <select name="session_id" id="filter_session" class="form-control">
                                    <option value="">{{ __('All Sessions') }}</option>
                                    @foreach($sessions as $session)
                                        <option value="{{ $session->id }}"
                                            {{ (string) ($filters['session_id'] ?? '') === (string) $session->id ? 'selected' : '' }}>
                                            {{ $session->title }}
                                            @if($session->current) — {{ __('Current') }} @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="filter_year" class="form-label">{{ __('Year / Level') }}</label>
                                <select name="year" id="filter_year" class="form-control">
                                    <option value="">{{ __('All Years') }}</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ ($filters['year'] ?? '') == $year ? 'selected' : '' }}>
                                            {{ __('Year') }} {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="filter_semester_type" class="form-label">{{ __('Semester Type') }}</label>
                                <select name="semester_type" id="filter_semester_type" class="form-control">
                                    <option value="">{{ __('All Semesters') }}</option>
                                    @foreach($semesterTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}" {{ ($filters['semester_type'] ?? '') == $typeValue && ($filters['semester_type'] ?? '') !== '' ? 'selected' : '' }}>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="filter_state" class="form-label">{{ __('Workflow State') }}</label>
                                <select name="state" id="filter_state" class="form-control">
                                    <option value="">{{ __('All States') }}</option>
                                    @foreach($states as $state)
                                        @php $value = \Illuminate\Support\Str::lower($state); @endphp
                                        <option value="{{ $value }}" {{ ($filters['state'] ?? '') === $value ? 'selected' : '' }}>
                                            {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $state)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-info me-2"><i class="fas fa-search"></i> {{ __('Filter') }}</button>
                                <a href="{{ route('admin.resit-requests.index') }}?session_id=" class="btn btn-light border" title="{{ __('Reset all filters') }}">{{ __('Reset') }}</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        @if($requests->isEmpty())
                            <div class="alert alert-warning mb-0" role="alert">
                                {{ __('No resit requests found for the selected filters.') }}
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Reference') }}</th>
                                            <th>{{ __('Student') }}</th>
                                            <th>{{ __('Course') }}</th>
                                            <th>{{ __('Program') }}</th>
                                            <th>{{ __('Session') }}</th>
                                            <th>{{ __('Semester') }}</th>
                                            <th>{{ __('Fee') }}</th>
                                            <th>{{ __('Payment') }}</th>
                                            <th>{{ __('State') }}</th>
                                            <th>{{ __('Resit Schedule') }}</th>
                                            <th>{{ __('Updated') }}</th>
                                            <th>{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($requests as $resitRequest)
                                            @php
                                                $meta = $workflowMeta->get($resitRequest->id) ?? [];
                                                $student = optional(optional($resitRequest->studentEnroll)->student);
                                                $program = optional(optional($resitRequest->studentEnroll)->program);
                                                $stateLabel = $meta['label'] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $resitRequest->workflow_state ?? \App\Models\ResitRequest::STATE_REQUESTED));
                                                $stateBadge = $meta['badge'] ?? 'secondary';
                                                $transitions = $meta['transitions'] ?? [];
                                                $requiresScheduling = in_array(\App\Models\ResitRequest::STATE_SCHEDULED, $transitions, true);
                                                $studentProfileUrl = $student && $student->id ? route('admin.student.show', $student->id) : null;
                                            @endphp
                                            <tr>
                                                <td>#{{ $resitRequest->id }}</td>
                                                <td>
                                                    @php
                                                        $studentFullName = trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: __('Unknown');
                                                    @endphp
                                                    @if($studentProfileUrl)
                                                        <a href="{{ $studentProfileUrl }}"><strong>{{ $studentFullName }}</strong></a>
                                                    @else
                                                        <strong>{{ $studentFullName }}</strong>
                                                    @endif
                                                    @if($student->student_id)
                                                        <div><small class="text-muted">{{ $student->student_id }}</small></div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $subject = $resitRequest->subject;
                                                    @endphp
                                                    <div><strong>{{ $subject?->title ?? __('No course') }}</strong></div>
                                                    @if($subject?->code)
                                                        <small class="text-muted">{{ $subject->code }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <small>{{ $program->shortcode ?? $program->title ?? __('N/A') }}</small>
                                                </td>
                                                <td>
                                                    <div>{{ optional($resitRequest->session)->title }}</div>
                                                    <small class="text-muted">{{ __('Original Session') }}</small>
                                                </td>
                                                <td>
                                                    @php $enrollSemester = optional(optional($resitRequest->studentEnroll)->semester); @endphp
                                                    <div>{{ $enrollSemester->title ?? __('N/A') }}</div>
                                                    @if($enrollSemester->year)
                                                        <small class="text-muted">{{ __('Year') }} {{ $enrollSemester->year }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($resitRequest->fee_amount > 0)
                                                        {{ number_format($resitRequest->fee_amount, 2) }}
                                                    @else
                                                        <span class="text-muted">{{ __('Waived') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $paymentBadge = match($resitRequest->payment_status) {
                                                            \App\Models\ResitRequest::PAYMENT_PAID => 'success',
                                                            \App\Models\ResitRequest::PAYMENT_WAIVED => 'info',
                                                            \App\Models\ResitRequest::PAYMENT_PARTIAL => 'warning',
                                                            \App\Models\ResitRequest::PAYMENT_CANCELLED => 'danger',
                                                            default => 'secondary',
                                                        };
                                                    @endphp
                                                    <span class="badge bg-{{ $paymentBadge }}">
                                                        {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $resitRequest->payment_status ?? \App\Models\ResitRequest::PAYMENT_PENDING)) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $stateBadge }}">{{ $stateLabel }}</span>
                                                    @if($resitRequest->workflow_state === \App\Models\ResitRequest::STATE_SCHEDULED)
                                                        <i class="fas fa-check-circle text-success ms-1" title="{{ __('Auto-scheduled') }}"></i>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($resitRequest->resit_session_id && $resitRequest->resit_semester_id)
                                                        <div><strong>{{ optional($resitRequest->resitSession)->title }}</strong></div>
                                                        <small class="text-primary">{{ optional($resitRequest->resitSemester)->title }}</small>
                                                        <div class="mt-1">
                                                            <span class="badge bg-success" style="font-size: 9px;">
                                                                <i class="fas fa-robot"></i> {{ __('Auto-scheduled') }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <span class="text-muted small">{{ __('Not yet scheduled') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>{{ optional($resitRequest->state_changed_at)->format('d M Y H:i') ?? __('Never') }}</div>
                                                    <small class="text-muted">{{ __('Created:') }} {{ $resitRequest->created_at?->format('d M Y') }}</small>
                                                </td>
                                                <td>
                                                    @if(in_array($resitRequest->workflow_state, [\App\Models\ResitRequest::STATE_REQUESTED, \App\Models\ResitRequest::STATE_AWAITING_PAYMENT]))
                                                        <button type="button" class="btn btn-sm btn-warning btn-igrade"
                                                            data-bs-toggle="modal" data-bs-target="#iGradeModal"
                                                            data-id="{{ $resitRequest->id }}"
                                                            data-student="{{ $studentFullName }}"
                                                            data-matricule="{{ $student->student_id ?? '' }}"
                                                            data-course-code="{{ $resitRequest->subject?->code ?? '' }}"
                                                            data-course-title="{{ $resitRequest->subject?->title ?? '' }}"
                                                            data-fee="{{ number_format($resitRequest->fee_amount, 2) }}"
                                                            data-program="{{ $program->shortcode ?? $program->title ?? '' }}"
                                                            data-session="{{ optional($resitRequest->session)->title }}"
                                                            data-semester="{{ $enrollSemester->title ?? '' }}">
                                                            <i class="fas fa-user-check"></i> {{ __('I-Grade') }}
                                                        </button>
                                                    @endif
                                                    @if($meta['can_cancel_resit'] ?? false)
                                                        <button type="button" class="btn btn-sm btn-danger btn-cancel-resit"
                                                            data-bs-toggle="modal" data-bs-target="#cancelResitModal"
                                                            data-id="{{ $resitRequest->id }}"
                                                            data-student="{{ $studentFullName }}"
                                                            data-matricule="{{ $student->student_id ?? '' }}"
                                                            data-course-code="{{ $resitRequest->subject?->code ?? '' }}"
                                                            data-course-title="{{ $resitRequest->subject?->title ?? '' }}"
                                                            data-fee="{{ number_format($resitRequest->fee_amount, 2) }}"
                                                            data-payment="{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $resitRequest->payment_status ?? '')) }}"
                                                            data-program="{{ $program->shortcode ?? $program->title ?? '' }}"
                                                            data-session="{{ optional($resitRequest->session)->title }}"
                                                            data-semester="{{ $enrollSemester->title ?? '' }}"
                                                            data-resit-session="{{ optional($resitRequest->resitSession)->title }}"
                                                            data-resit-semester="{{ optional($resitRequest->resitSemester)->title }}">
                                                            <i class="fas fa-times-circle"></i> {{ __('Cancel Resit') }}
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3">
                                {{ $requests->withQueryString()->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- I-Grade Modal -->
<div class="modal fade" id="iGradeModal" tabindex="-1" aria-labelledby="iGradeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="iGradeModalLabel">
                    <i class="fas fa-user-check me-2"></i>{{ __('Apply I-Grade (Fee Waiver)') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="iGradeForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>{{ __('Warning:') }}</strong>
                        {{ __('This action will waive the resit fee and automatically schedule the student for the next resit semester. This action cannot be undone.') }}
                    </div>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        {{ __('I-Grade should only be applied for students with valid reasons, such as being absent from the exam with proper justification.') }}
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <strong>{{ __('Course Details') }}</strong>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="fw-bold ps-3" style="width: 35%;">{{ __('Student') }}</td>
                                    <td><span id="igrade-student"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Matricule') }}</td>
                                    <td><span id="igrade-matricule"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Course') }}</td>
                                    <td><span id="igrade-course"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Program') }}</td>
                                    <td><span id="igrade-program"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Session') }}</td>
                                    <td><span id="igrade-session"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Semester') }}</td>
                                    <td><span id="igrade-semester"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Fee to Waive') }}</td>
                                    <td><span id="igrade-fee" class="text-danger fw-bold"></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="igrade-reason" class="form-label fw-bold">
                            {{ __('Reason for Fee Waiver') }} <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" id="igrade-reason" class="form-control" rows="3" required
                            placeholder="{{ __('Enter the reason for applying I-Grade (e.g., student was absent from exam with valid justification)...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check me-1"></i>{{ __('Apply I-Grade') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Resit Modal -->
<div class="modal fade" id="cancelResitModal" tabindex="-1" aria-labelledby="cancelResitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancelResitModalLabel">
                    <i class="fas fa-times-circle me-2"></i>{{ __('Cancel Scheduled Resit') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="cancelResitForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>{{ __('Warning:') }}</strong>
                        {{ __('This will cancel the scheduled resit for this course. The student will need to re-request the resit if they wish to retake the course in a future resit semester.') }}
                    </div>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        <strong>{{ __('No Refund:') }}</strong>
                        {{ __('The resit fee that was paid will NOT be refunded. By proceeding, you confirm the student has been informed about this.') }}
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <strong>{{ __('Resit Details') }}</strong>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="fw-bold ps-3" style="width: 40%;">{{ __('Student') }}</td>
                                    <td><span id="cancel-student"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Matricule') }}</td>
                                    <td><span id="cancel-matricule"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Course') }}</td>
                                    <td><span id="cancel-course"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Program') }}</td>
                                    <td><span id="cancel-program"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Original Session') }}</td>
                                    <td><span id="cancel-session"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Original Semester') }}</td>
                                    <td><span id="cancel-semester"></span></td>
                                </tr>
                                <tr class="table-info">
                                    <td class="fw-bold ps-3">{{ __('Scheduled Resit Session') }}</td>
                                    <td><strong><span id="cancel-resit-session"></span></strong></td>
                                </tr>
                                <tr class="table-info">
                                    <td class="fw-bold ps-3">{{ __('Scheduled Resit Semester') }}</td>
                                    <td><strong><span id="cancel-resit-semester"></span></strong></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold ps-3">{{ __('Fee Paid') }}</td>
                                    <td>
                                        <span id="cancel-fee"></span>
                                        <span class="badge bg-secondary ms-1" id="cancel-payment-badge"></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cancel-reason" class="form-label fw-bold">
                            {{ __('Reason for Cancellation') }} <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" id="cancel-reason" class="form-control" rows="3" required
                            placeholder="{{ __('Enter the reason for cancelling this resit (e.g., student requested cancellation, administrative decision)...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle me-1"></i>{{ __('Cancel Resit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // I-Grade Modal
    var iGradeModal = document.getElementById('iGradeModal');
    if (iGradeModal) {
        iGradeModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            document.getElementById('iGradeForm').action = '{{ url("admin/exam/resit-requests") }}/' + id + '/i-grade';

            document.getElementById('igrade-student').textContent = button.getAttribute('data-student');
            document.getElementById('igrade-matricule').textContent = button.getAttribute('data-matricule');

            var code = button.getAttribute('data-course-code');
            var title = button.getAttribute('data-course-title');
            document.getElementById('igrade-course').textContent = code ? code + ' \u2014 ' + title : title;

            document.getElementById('igrade-program').textContent = button.getAttribute('data-program');
            document.getElementById('igrade-session').textContent = button.getAttribute('data-session');
            document.getElementById('igrade-semester').textContent = button.getAttribute('data-semester');
            document.getElementById('igrade-fee').textContent = button.getAttribute('data-fee');
            document.getElementById('igrade-reason').value = '';
        });
    }

    // Cancel Resit Modal
    var cancelResitModal = document.getElementById('cancelResitModal');
    if (cancelResitModal) {
        cancelResitModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            document.getElementById('cancelResitForm').action = '{{ url("admin/exam/resit-requests") }}/' + id + '/cancel-resit';

            document.getElementById('cancel-student').textContent = button.getAttribute('data-student');
            document.getElementById('cancel-matricule').textContent = button.getAttribute('data-matricule');

            var code = button.getAttribute('data-course-code');
            var title = button.getAttribute('data-course-title');
            document.getElementById('cancel-course').textContent = code ? code + ' \u2014 ' + title : title;

            document.getElementById('cancel-program').textContent = button.getAttribute('data-program');
            document.getElementById('cancel-session').textContent = button.getAttribute('data-session');
            document.getElementById('cancel-semester').textContent = button.getAttribute('data-semester');
            document.getElementById('cancel-resit-session').textContent = button.getAttribute('data-resit-session');
            document.getElementById('cancel-resit-semester').textContent = button.getAttribute('data-resit-semester');
            document.getElementById('cancel-fee').textContent = button.getAttribute('data-fee');
            document.getElementById('cancel-payment-badge').textContent = button.getAttribute('data-payment');
            document.getElementById('cancel-reason').value = '';
        });
    }
});
</script>
@endsection
