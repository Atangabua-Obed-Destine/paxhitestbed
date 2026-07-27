@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span class="d-block m-t-5">{{ __('Request resit for failed courses') }}</span>
                    </div>
                    <div class="card-block">
                        @if(isset($current_enrollment))
                        <!-- Current Program Info -->
                        <div class="alert alert-info mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-graduation-cap fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-1 text-white"><strong>{{ __('Current Program') }}</strong></h6>
                                    <p class="mb-0" style="font-size: 14px;">
                                        <strong>{{ $current_enrollment->program->title ?? '' }}</strong>
                                        <span class="mx-2">|</span>
                                        <strong>{{ __('Matricule:') }}</strong> {{ $current_enrollment->matricule ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        <!-- Search Form -->
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-4">
                                    <label for="session_id">{{ __('field_session') }} <span>*</span></label>
                                    <select class="form-control" name="session_id" id="session_id" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $session)
                                        <option value="{{ $session->id }}" @if($selected_session == $session->id) selected @endif>
                                            {{ $session->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_session') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="semester_id">{{ __('field_semester') }} <span>*</span></label>
                                    <select class="form-control" name="semester_id" id="semester_id" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($semesters as $semester)
                                        <option value="{{ $semester->id }}" @if($selected_semester == $semester->id) selected @endif>
                                            {{ $semester->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_semester') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label>&nbsp;</label><br>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                    <a href="{{ route($route.'.history') }}" class="btn btn-info"><i class="fas fa-history"></i> {{ __('My Requests') }}</a>
                                </div>
                            </div>
                        </form>
                        
                        @if(!isset($current_enrollment))
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> {{ __('No active enrollment found. Please contact administration.') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($is_resit_semester)
        <!-- RESIT SEMESTER SELECTED (BLOCKING) -->
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-danger" style="background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%); border: none; color: white;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-3x me-3"></i>
                        <div>
                            <h5 class="mb-1 text-white"><strong>{{ __('Invalid Selection') }}</strong></h5>
                            <p class="mb-0" style="font-size: 14px; opacity: 0.95;">
                                {{ __('Resits cannot be requested from a resit semester. Any courses failed during a resit semester must be carried over to the next regular semester. Please select a regular semester from the dropdown above to view your failed courses.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @elseif(isset($current_is_resit) && $current_is_resit)
        <!-- CURRENTLY IN RESIT SEMESTER (INFORMATIONAL) -->
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info" style="border-left: 4px solid #17a2b8;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                        <div>
                            <h6 class="mb-1"><strong>{{ __('You are currently enrolled in a Resit Semester') }}</strong></h6>
                            <p class="mb-0" style="font-size: 14px;">
                                {{ __('You can continue to request resits for any remaining failed courses from your regular semesters. Approved resits will be automatically added to your current resit semester enrollment.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if(isset($failed_courses) && count($failed_courses) > 0 && !$is_resit_semester)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Failed Courses') }}</h5>
                        <span class="d-block m-t-5">{{ __('Courses with marks below 50% and all exam types published') }}</span>
                    </div>
                    <div class="card-block">
                        <!-- PROGRESSION INFO & WARNINGS -->
                        @if(isset($progression_info))
                            @if($progression_info['can_progress'])
                                <!-- Ready to progress to resit semester -->
                                <div class="alert alert-success">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-check-circle fa-2x me-3"></i>
                                        <div>
                                            <strong>{{ __('Great News!') }}</strong>
                                            <p class="mb-0">
                                                {{ __('All your failed courses have been resolved. You will be automatically enrolled in the resit semester to retake:') }}
                                            </p>
                                            <ul class="mb-0 mt-2">
                                                @foreach($progression_info['scheduled_courses'] as $sc)
                                                <li><strong>{{ $sc['subject_code'] }}</strong> - {{ $sc['subject_title'] }}</li>
                                                @endforeach
                                            </ul>
                                            <div class="mt-2">
                                                <strong>{{ __('Resit Semester:') }}</strong> {{ $progression_info['resit_semester']->title ?? '' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif(isset($progression_info['unresolved_courses']) && !empty($progression_info['unresolved_courses']))
                                <!-- Has unresolved courses blocking progression -->
                                <div class="alert alert-warning">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-triangle fa-2x me-3 mt-1"></i>
                                        <div>
                                            <strong>{{ __('Progression Blocked') }}</strong>
                                            <p class="mb-2">{{ __('You have unresolved failed courses that are blocking your progression:') }}</p>
                                            <ul class="mb-0">
                                                @foreach($progression_info['unresolved_courses'] as $uc)
                                                <li>
                                                    <strong>{{ $uc['subject_code'] }}</strong> - {{ $uc['subject_title'] }}
                                                    <span class="badge badge-warning ms-2">
                                                        @if($uc['status'] === 'no_request')
                                                            {{ __('No Request Made') }}
                                                        @elseif($uc['status'] === 'pending_payment')
                                                            {{ __('Pending Payment') }}
                                                        @else
                                                            {{ __('Unresolved') }}
                                                        @endif
                                                    </span>
                                                </li>
                                                @endforeach
                                            </ul>
                                            <div class="mt-3 p-2" style="background: rgba(255,255,255,0.9); border-radius: 5px;">
                                                <strong><i class="fas fa-lightbulb me-1"></i> {{ __('What you need to do:') }}</strong>
                                                <ul class="mb-0 mt-1">
                                                    <li>{{ __('Request resit for any course you want to retake, OR click "Don\'t Resit Course" to decline.') }}</li>
                                                </ul>
                                                <div class="mt-2 p-2" style="background: #f0f9ff; border-radius: 4px; border-left: 3px solid #17a2b8;">
                                                    <strong><i class="fas fa-money-bill-wave me-1"></i> {{ __('How to Pay Your Resit Fee:') }}</strong>
                                                    <ul class="mb-0 mt-1" style="list-style: disc; padding-left: 18px;">
                                                        <li><i class="fas fa-laptop text-primary"></i> <strong>{{ __('Online:') }}</strong> {{ __('Go to') }} <a href="{{ route('student.fees.index') }}" class="font-weight-bold">{{ __('My Fees') }}</a> {{ __('and pay directly from your portal.') }}</li>
                                                        <li><i class="fas fa-money-bill-alt text-info"></i> <strong>{{ __('Cash:') }}</strong> {{ __('Pay cash directly at the Finance Office.') }}</li>
                                                        <li><i class="fas fa-university text-success"></i> <strong>{{ __('Bank/Mobile Money:') }}</strong> {{ __('Pay via bank or mobile money, then take your payment receipt to the Finance Office for verification.') }}</li>
                                                    </ul>
                                                </div>
                                                <p class="mb-0 mt-2 small text-muted">
                                                    <i class="fas fa-info-circle"></i> {{ __('Once all failed courses are resolved (either scheduled for resit or declined), you will automatically be enrolled in the resit semester.') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <!-- Default info message -->
                            <div class="alert alert-info" style="border-left: 4px solid #17a2b8;">
                                <h6 class="mb-2"><i class="fas fa-info-circle"></i> <strong>{{ __('How Resit Works') }}</strong></h6>
                                <ol class="mb-2" style="padding-left: 18px; margin-bottom: 8px;">
                                    <li>{{ __('Click') }} <strong>"{{ __('Request Resit') }}"</strong> {{ __('on any failed course below. A resit fee will be assigned automatically.') }}</li>
                                    <li>
                                        <strong>{{ __('Pay your resit fee using one of these options:') }}</strong>
                                        <ul class="mt-1 mb-1" style="list-style: disc; padding-left: 18px;">
                                            <li><i class="fas fa-laptop text-primary"></i> <strong>{{ __('Online:') }}</strong> {{ __('Go to') }} <a href="{{ route('student.fees.index') }}" class="font-weight-bold">{{ __('My Fees') }}</a> {{ __('and pay directly from your portal.') }}</li>
                                            <li><i class="fas fa-money-bill-alt text-info"></i> <strong>{{ __('Cash:') }}</strong> {{ __('Pay cash directly at the Finance Office.') }}</li>
                                            <li><i class="fas fa-university text-success"></i> <strong>{{ __('Bank/Mobile Money:') }}</strong> {{ __('Pay via bank or mobile money, then bring your receipt to the Finance Office for verification.') }}</li>
                                        </ul>
                                    </li>
                                    <li>{{ __('Once finance confirms your payment, your resit will be approved and you will be scheduled automatically.') }}</li>
                                </ol>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_code') }}</th>
                                        <th>{{ __('field_subject') }}</th>
                                        <th>{{ __('field_credit_hour') }}</th>
                                        <th>{{ __('Total Marks') }}</th>
                                        <th>{{ __('field_grade') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($failed_courses as $key => $course)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $course['subject']->code }}</td>
                                        <td>{{ $course['subject']->title }}</td>
                                        <td>{{ $course['subject']->credit_hour }}</td>
                                        <td>
                                            <span class="badge badge-danger">{{ number_format($course['total_marks'], 2) }}%</span>
                                        </td>
                                        <td>
                                            @if($course['grade'])
                                            <span class="badge badge-secondary">{{ $course['grade'] }}</span>
                                            @else
                                            <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($course['existing_request'])
                                                @php
                                                    $state = $course['existing_request']->workflow_state;
                                                    $badge_class = match($state) {
                                                        'requested' => 'secondary',
                                                        'awaiting_payment' => 'warning',
                                                        'finance_review' => 'info',
                                                        'approved' => 'primary',
                                                        'scheduled' => 'success',
                                                        'rejected' => 'danger',
                                                        'cancelled' => 'dark',
                                                        'declined' => 'warning',
                                                        default => 'secondary',
                                                    };
                                                @endphp
                                                <span class="badge badge-{{ $badge_class }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</span>
                                                @if($state === 'awaiting_payment' && $course['existing_request']->fee_amount > 0)
                                                <br><small class="text-danger mt-1"><strong>{{ number_format($course['existing_request']->fee_amount, 0) }} {{ __('XAF') }}</strong> — {{ __('unpaid') }}</small>
                                                @endif
                                            @else
                                                <span class="badge badge-light">{{ __('Not Requested') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($course['existing_request'])
                                                @php
                                                    $state = $course['existing_request']->workflow_state;
                                                    $cancellable = in_array($state, ['requested', 'awaiting_payment']);
                                                    $isDeclined = $state === 'declined';
                                                    $isCancelled = $state === 'cancelled';
                                                    $isRejected = $state === 'rejected';
                                                @endphp
                                                
                                                @if($isDeclined)
                                                    <!-- Declined - can undo -->
                                                    <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                                                        <span class="text-muted">
                                                            <i class="fas fa-ban"></i> {{ __('Declined') }}
                                                        </span>
                                                        <form method="post" action="{{ route($route.'.undo-decline', $course['existing_request']->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('Undo your decline decision? You will be able to request a resit or decline again.') }}');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-undo"></i> {{ __('Undo') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                @elseif($isCancelled || $isRejected)
                                                    <!-- Cancelled or rejected - can request again or decline -->
                                                    <div class="btn-group" role="group">
                                                        <form method="post" action="{{ route($route.'.store') }}" style="display:inline;" onsubmit="return confirm('{{ __('Are you sure you want to request a resit for this course? A resit fee will be assigned.') }}');">
                                                            @csrf
                                                            <input type="hidden" name="student_enroll_id" value="{{ $enrollment->id }}">
                                                            <input type="hidden" name="subject_id" value="{{ $course['subject']->id }}">
                                                            <input type="hidden" name="session_id" value="{{ $enrollment->session_id }}">
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-paper-plane"></i> {{ __('Request Resit') }}
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="post" action="{{ route($route.'.decline') }}" style="display:inline; margin-left: 5px;" onsubmit="return confirm('{{ __('Are you sure you do NOT want to resit this course? This means you accept the failing grade and will move forward without retaking this course.') }}');">
                                                            @csrf
                                                            <input type="hidden" name="student_enroll_id" value="{{ $enrollment->id }}">
                                                            <input type="hidden" name="subject_id" value="{{ $course['subject']->id }}">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-ban"></i> {{ __('Don\'t Resit Course') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                @elseif($cancellable)
                                                    <!-- Active request - show payment options if awaiting payment -->
                                                    <div class="d-flex align-items-center flex-wrap" style="gap:6px;">
                                                        @if($state === 'awaiting_payment' && $course['existing_request']->fee_id)
                                                        <a href="{{ route('student.fees.index') }}" class="btn btn-sm btn-success" title="{{ __('Pay this fee online from your Fees page') }}">
                                                            <i class="fas fa-credit-card"></i> {{ __('Pay Online') }}
                                                        </a>
                                                        @endif
                                                        <form method="post" action="{{ route($route.'.cancel', $course['existing_request']->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('Are you sure you want to cancel this resit request? This action cannot be undone.') }}');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-times"></i> {{ __('Cancel') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <!-- Request in advanced stage (approved, scheduled, etc.) -->
                                                    <span class="text-muted">{{ __('Cannot Cancel') }}</span>
                                                @endif
                                            @else
                                                <!-- No request exists - show both buttons -->
                                                <div class="btn-group" role="group">
                                                    <form method="post" action="{{ route($route.'.store') }}" style="display:inline;" onsubmit="return confirm('{{ __('Are you sure you want to request a resit for this course? A resit fee will be assigned.') }}');">
                                                        @csrf
                                                        <input type="hidden" name="student_enroll_id" value="{{ $enrollment->id }}">
                                                        <input type="hidden" name="subject_id" value="{{ $course['subject']->id }}">
                                                        <input type="hidden" name="session_id" value="{{ $enrollment->session_id }}">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-paper-plane"></i> {{ __('Request Resit') }}
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="post" action="{{ route($route.'.decline') }}" style="display:inline; margin-left: 5px;" onsubmit="return confirm('{{ __('Are you sure you do NOT want to resit this course? This means you accept the failing grade and will move forward without retaking this course.') }}');">
                                                        @csrf
                                                        <input type="hidden" name="student_enroll_id" value="{{ $enrollment->id }}">
                                                        <input type="hidden" name="subject_id" value="{{ $course['subject']->id }}">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-ban"></i> {{ __('Don\'t Resit Course') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @elseif(isset($selected_session) && isset($selected_semester))
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> {{ __('Congratulations! You have no failed courses for the selected session and semester.') }}
                </div>
            </div>
        </div>
        @elseif(isset($failed_courses) && !$is_resit_semester)
        <div class="row">
            <div class="col-md-12">
                @if(isset($existing_requests) && count($existing_requests) > 0)
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> {{ __('You have no remaining failed courses to request resits for. All your failed courses have already been requested/scheduled. Please check your recent requests below.') }}
                </div>
                @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> {{ __('No failed courses found for this semester, or course marks are not published yet.') }}
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Existing Requests Summary -->
        @if(count($existing_requests) > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Recent Resit Requests') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_subject') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('Request Date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('Payment') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($existing_requests->take(5) as $key => $req)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $req->subject->code ?? '' }} - {{ $req->subject->title ?? '' }}</td>
                                        <td>{{ $req->session->title ?? '' }}</td>
                                        <td>{{ $req->studentEnroll->semester->title ?? 'N/A' }}</td>
                                        <td>{{ $req->created_at->format('d M Y') }}</td>
                                        <td>
                                            @php
                                                $state = $req->workflow_state;
                                                $badge_class = match($state) {
                                                    'requested' => 'secondary',
                                                    'awaiting_payment' => 'warning',
                                                    'finance_review' => 'info',
                                                    'approved' => 'primary',
                                                    'scheduled' => 'success',
                                                    'rejected' => 'danger',
                                                    'cancelled' => 'dark',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-{{ $badge_class }}">{{ ucfirst(str_replace('_', ' ', $state)) }}</span>
                                        </td>
                                        <td>
                                            @php
                                                $payment = $req->payment_status;
                                                $payment_badge = match($payment) {
                                                    'paid' => 'success',
                                                    'partial' => 'info',
                                                    'pending' => 'warning',
                                                    'cancelled' => 'danger',
                                                    'waived' => 'primary',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-{{ $payment_badge }}">{{ ucfirst($payment) }}</span>
                                            @if($req->fee_amount > 0)
                                            <br><small>{{ number_format($req->fee_amount, 0) }} {{ __('XAF') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(in_array($req->workflow_state, ['awaiting_payment']) && $req->payment_status === 'pending' && $req->fee_id)
                                                <a href="{{ route('student.fees.index') }}" class="btn btn-sm btn-success" title="{{ __('Pay online from your fees page') }}">
                                                    <i class="fas fa-credit-card"></i> {{ __('Pay') }}
                                                </a>
                                            @elseif($req->payment_status === 'paid')
                                                <span class="text-success"><i class="fas fa-check-circle"></i> {{ __('Paid') }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(count($existing_requests) > 5)
                        <div class="text-center mt-3">
                            <a href="{{ route($route.'.history') }}" class="btn btn-sm btn-info">{{ __('View All Requests') }}</a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
