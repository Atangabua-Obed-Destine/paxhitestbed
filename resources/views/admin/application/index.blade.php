@extends('admin.layouts.master')
@section('title', $title)
@section('page_css')
<style>
    /* Approval column: one dot per step in the chain. */
    .approval-cell { cursor: help; }
    .approval-track { display: flex; align-items: center; gap: 3px; margin-top: 4px; }
    .approval-dot {
        width: 9px; height: 9px; border-radius: 50%;
        background: #e3e6ed; border: 1px solid #c5ccd8;
    }
    .approval-dot.is-done { background: #2ed8b6; border-color: #2ed8b6; }
    .approval-dot.is-current { background: #fff; border-width: 2px; }
    .approval-dot.is-current.is-info { border-color: #4099ff; }
    .approval-dot.is-current.is-warning { border-color: #ffb64d; }
    .approval-dot.is-current.is-danger { border-color: #ff5370; }
</style>
@endsection
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="degree_type">{{ __('Degree Type') }}</label>
                                    <select class="form-control" name="degree_type" id="degree_type">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( ($degreeTypes ?? []) as $dt )
                                        <option value="{{ $dt->id }}" @if( ($selected_degree_type ?? '0') == $dt->id) selected @endif>{{ $dt->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('Intake') }}</label>
                                    <select class="form-control" name="session" id="session">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( ($sessions ?? []) as $s )
                                        <option value="{{ $s->id }}" @if( ($selected_session ?? '0') == $s->id) selected @endif>{{ $s->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control" name="program" id="program">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $programs as $program )
                                        <option value="{{ $program->id }}" @if( $selected_program == $program->id) selected @endif>{{ $program->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_program') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="status">{{ __('field_status') }}</label>
                                    <select class="form-control" name="status" id="status">
                                        <option value="">{{ __('All submitted') }}</option>
                                        <option value="1" @if( $selected_status === '1' ) selected @endif>{{ __('status_pending') }}</option>
                                        <option value="2" @if( $selected_status === '2' ) selected @endif>{{ __('status_approved') }}</option>
                                        <option value="0" @if( $selected_status === '0' ) selected @endif>{{ __('status_rejected') }}</option>
                                        {{-- Opt in: a draft is a form still being filled in, not
                                             something admissions has been asked to act on. --}}
                                        <option value="draft" @if( $selected_status === 'draft' ) selected @endif>{{ __('Not yet submitted') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_status') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="start_date">{{ __('field_from_date') }}</label>
                                    <input type="date" class="form-control date" name="start_date" id="start_date" value="{{ $selected_start_date }}" required>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_from_date') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="end_date">{{ __('field_to_date') }}</label>
                                    <input type="date" class="form-control date" name="end_date" id="end_date" value="{{ $selected_end_date }}" required>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_to_date') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="registration_no">{{ __('field_registration_no') }}</label>
                                    <input type="text" class="form-control" name="registration_no" id="registration_no" value="{{ $selected_registration_no }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_registration_no') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="applicant">{{ __('Applicant (name, email, phone)') }}</label>
                                    <input type="text" class="form-control" name="applicant" id="applicant" value="{{ $selected_applicant ?? '' }}" placeholder="{{ __('Search by name, email or phone') }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>

                            {{-- The admissions board report. The buttons submit this same
                                 form to the report, so it covers exactly the applications
                                 these filters select — no need to search first. --}}
                            <div class="row align-items-end border-top pt-3 mt-2">
                                <div class="col-md-6 mb-2">
                                    <h6 class="mb-1"><i class="fas fa-chart-bar"></i> {{ __('Admissions board report') }}</h6>
                                    <p class="text-muted small mb-0">{{ __('First, second and third choices, applicants by faculty and programme, and which programmes have enough applicants to open — for the applications matching the filters above.') }}</p>
                                </div>
                                <div class="form-group col-md-2 mb-2">
                                    <label for="min_class">{{ __('Minimum class size') }}</label>
                                    <input type="number" min="1" max="500" class="form-control" name="min_class" id="min_class" value="{{ request('min_class', \App\Services\ApplicationDemandReport::DEFAULT_MIN_CLASS) }}" title="{{ __('A programme needs at least this many first-choice applicants to count as having enough.') }}">
                                </div>
                                <div class="form-group col-md-4 mb-2">
                                    <button type="submit" class="btn btn-primary" formaction="{{ route('admin.application.report.pdf') }}">
                                        <i class="fas fa-file-pdf"></i> {{ __('Board report (PDF)') }}
                                    </button>
                                    <button type="submit" class="btn btn-success" formaction="{{ route('admin.application.report.excel') }}">
                                        <i class="fas fa-file-excel"></i> {{ __('Workbook (Excel)') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @isset($rows)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="export-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_registration_no') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_gender') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('Degree Type') }}</th>
                                        <th>{{ __('Intake') }}</th>
                                        <th>{{ __('field_apply_date') }}</th>
                                        <th>{{ __('Stage') }}</th>
                                        <th>{{ __('Approval') }}</th>
                                        <th>{{ __('Admission Fee') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <a href="{{ route($route.'.show', $row->id) }}">
                                            #{{ $row->registration_no }}
                                            </a>
                                        </td>
                                        <td>{{ $row->first_name }} {{ $row->last_name }}</td>
                                        <td>
                                            @if( $row->gender == 1 )
                                            {{ __('gender_male') }}
                                            @elseif( $row->gender == 2 )
                                            {{ __('gender_female') }}
                                            @elseif( $row->gender == 3 )
                                            {{ __('gender_other') }}
                                            @endif
                                        </td>
                                        <td>{{ $row->program->title ?? '' }}</td>
                                        <td>{{ optional($row->degreeType)->title ?? '' }}</td>
                                        <td>{{ optional($row->session)->title ?? $row->academic_year ?? '' }}</td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->apply_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->apply_date)) }}
                                            @endif
                                        </td>
                                        <td>{{ $row->progress_label }}</td>
                                        <td>
                                            {{-- Where the application stands in the approval
                                                 chain. The label is plain text so it exports
                                                 cleanly; the tracker and tooltip are for the
                                                 screen. --}}
                                            @php $approval = $row->approvalSummary(); @endphp
                                            <div class="approval-cell" title="{{ $approval['detail'] }}">
                                                <span class="badge badge-{{ $approval['tone'] }}">{{ $approval['label'] }}</span>
                                                @if($approval['state'] !== 'not_submitted' && $approval['state'] !== 'approved_before_flow')
                                                    <div class="approval-track" aria-hidden="true">
                                                        @foreach($approval['steps'] as $stepKey => $step)
                                                            <span class="approval-dot {{ $step['approved'] ? 'is-done' : ($approval['step'] === $stepKey ? 'is-current is-'.$approval['tone'] : '') }}"></span>
                                                        @endforeach
                                                        <small class="text-muted ms-1">{{ $approval['done'] }}/{{ $approval['total'] }}</small>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $feeEnabledValue = env('ADMISSION_FEE_ENABLED', 'true');
                                                $admissionFeeEnabled = in_array(strtolower($feeEnabledValue), ['true', '1', 'yes', 'on']);
                                                $hasFee = $row->admissionFee !== null;
                                                $isPaid = $hasFee && $row->admissionFee->status == 1;
                                                $hasBalance = $hasFee && $row->admissionFee->remaining_balance > 0;
                                                $hasPendingReceipt = $hasFee && $row->admissionFee->paymentReceipts && 
                                                                     $row->admissionFee->paymentReceipts->where('verification_status', 'pending')->count() > 0;
                                            @endphp
                                            
                                            @if(!$admissionFeeEnabled)
                                                <span class="badge badge-secondary" title="Admission fee requirement is disabled">
                                                    <i class="fas fa-info-circle"></i> Not Required
                                                </span>
                                            @elseif(!$hasFee)
                                                <span class="badge badge-warning" title="No fee assigned">
                                                    <i class="fas fa-exclamation-triangle"></i> No Fee
                                                </span>
                                            @elseif($isPaid)
                                                <span class="badge badge-success" title="Fee fully paid">
                                                    <i class="fas fa-check-circle"></i> Paid
                                                </span>
                                            @elseif($hasPendingReceipt)
                                                <span class="badge badge-info" title="Payment pending verification">
                                                    <i class="fas fa-clock"></i> Pending Verification
                                                </span>
                                            @elseif($hasBalance)
                                                <span class="badge badge-danger" title="Fee not paid">
                                                    <i class="fas fa-times-circle"></i> Unpaid
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ (int) $row->progress }}%;" aria-valuenow="{{ (int) $row->progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted">{{ (int) $row->progress }}%</small>
                                            <div class="mt-1">
                                                {{-- Read from stage, not status. The portal writes
                                                     status = 0 to mean "not submitted yet", while this
                                                     screen read anything other than 1 or 2 as rejected —
                                                     so every draft appeared here in red as though it had
                                                     been turned down. --}}
                                                @php
                                                    $stageBadge = [
                                                        'draft' => ['secondary', __('Not yet submitted')],
                                                        'submitted' => ['primary', __('status_pending')],
                                                        'under_review' => ['info', __('Under review')],
                                                        'decision_approved' => ['success', __('status_approved')],
                                                        'decision_rejected' => ['danger', __('status_rejected')],
                                                    ][$row->stage] ?? ['secondary', ucfirst(str_replace('_', ' ', (string) $row->stage))];
                                                @endphp
                                                <span class="badge badge-pill badge-{{ $stageBadge[0] }}">{{ $stageBadge[1] }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-icon btn-success btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            @php
                                                // Check if application can be reviewed
                                                $canReview = !$admissionFeeEnabled || !$hasFee || $isPaid;
                                            @endphp

                                            @if( $row->status == 1 )
                                            @can($access.'-create')
                                                @if($canReview)
                                                    <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm" title="Review Application">
                                                        <i class="fa-solid fa-right-from-bracket"></i>
                                                    </a>
                                                @else
                                                    <button type="button" class="btn btn-icon btn-secondary btn-sm" disabled title="Application cannot be reviewed until admission fee is paid">
                                                        <i class="fa-solid fa-lock"></i>
                                                    </button>
                                                @endif
                                            @endcan

                                            @can($access.'-edit')
                                                @if($canReview)
                                                    <button type="button" class="btn btn-icon btn-danger btn-sm" title="{{ __('status_rejected') }}" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $row->id }}">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-icon btn-secondary btn-sm" disabled title="Cannot reject until payment status is resolved">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                @endif
                                                <!-- Include Cancel modal -->
                                                @include($view.'.cancel')
                                            @endcan

                                            @elseif( $row->status == 0 )
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-success btn-sm" title="{{ __('status_pending') }}" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $row->id }}">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <!-- Include Cancel modal -->
                                            @include($view.'.cancel')
                                            @endcan
                                            @endif
                                            
                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <!-- Include Delete modal -->
                                            @include('admin.layouts.inc.delete')
                                            @endcan

                                            @if((int) $row->status === 2)
                                                @can($access.'-edit')
                                                    <a href="{{ route($route.'.acceptance-letter.download', $row->id) }}"
                                                       target="_blank"
                                                       class="btn btn-icon btn-outline-primary btn-sm"
                                                       title="{{ __('Download acceptance letter') }}">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <form action="{{ route($route.'.acceptance-letter.resend', $row->id) }}"
                                                          method="post" class="d-inline"
                                                          onsubmit="return confirm('{{ __('Resend the acceptance letter to') }} {{ $row->email }}?');">
                                                        @csrf
                                                        <button type="submit"
                                                                class="btn btn-icon btn-info btn-sm"
                                                                title="{{ __('Resend acceptance letter') }}">
                                                            <i class="fas fa-paper-plane"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
            </div>
            @endisset
            
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection