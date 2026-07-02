@extends('admin.layouts.master')
@section('title', $title)
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
                                        <option value="">{{ __('all') }}</option>
                                        <option value="1" @if( $selected_status == 1 ) selected @endif>{{ __('status_pending') }}</option>
                                        <option value="2" @if( $selected_status == 2 ) selected @endif>{{ __('status_approved') }}</option>
                                        <option value="0" @if( $selected_status == 0 ) selected @endif>{{ __('status_rejected') }}</option>
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
                                                @if( $row->status == 1 )
                                                <span class="badge badge-pill badge-primary">{{ __('status_pending') }}</span>
                                                @elseif( $row->status == 2 )
                                                <span class="badge badge-pill badge-success">{{ __('status_approved') }}</span>
                                                @else
                                                <span class="badge badge-pill badge-danger">{{ __('status_rejected') }}</span>
                                                @endif
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