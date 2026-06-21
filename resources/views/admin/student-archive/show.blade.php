@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Student Profile ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user"></i> {{ __('student_profile') }}</h5>
                        <div class="float-end">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                            @can($access.'-edit')
                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-primary btn-sm">
                                <i class="far fa-edit"></i> {{ __('btn_edit') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                @if(is_file('uploads/'.$path.'/'.$row->photo))
                                <img src="{{ asset('uploads/'.$path.'/'.$row->photo) }}" class="img-fluid rounded" alt="{{ $row->first_name }}">
                                @else
                                <img src="{{ asset('uploads/default.png') }}" class="img-fluid rounded" alt="{{ $row->first_name }}">
                                @endif
                            </div>
                            <div class="col-md-9">
                                <h4 class="mb-3">{{ $row->first_name }} {{ $row->last_name }}</h4>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th width="40%">{{ __('field_student_id') }}:</th>
                                                <td><strong>{{ $row->student_id }}</strong></td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_email') }}:</th>
                                                <td>{{ $row->email }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_phone') }}:</th>
                                                <td>{{ $row->phone }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_gender') }}:</th>
                                                <td>
                                                    @if($row->gender == 1)
                                                    {{ __('gender_male') }}
                                                    @elseif($row->gender == 2)
                                                    {{ __('gender_female') }}
                                                    @else
                                                    {{ __('gender_other') }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_date_of_birth') }}:</th>
                                                <td>
                                                    @if(isset($row->dob))
                                                    {{ date('d M Y', strtotime($row->dob)) }}
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th width="40%">{{ __('field_status') }}:</th>
                                                <td>
                                                    @if($row->statuses->isNotEmpty())
                                                        @foreach($row->statuses as $status)
                                                        <span class="badge badge-primary">{{ $status->title }}</span>
                                                        @endforeach
                                                    @else
                                                        <span class="badge badge-secondary">N/A</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_address') }}:</th>
                                                <td>{{ $row->present_address }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_country') }}:</th>
                                                <td>{{ $row->country ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_province') }}:</th>
                                                <td>{{ $row->present_province ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('field_district') }}:</th>
                                                <td>{{ $row->present_district ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrollment History -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-graduation-cap"></i> {{ __('enrollment_history') }}</h5>
                        <span class="text-muted">Total Enrollments: {{ $row->enrolls->count() }}</span>
                    </div>
                    <div class="card-block">
                        @if($row->enrolls->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_enrolled_date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row->enrolls as $key => $enroll)
                                    <tr class="{{ $enroll->status == 1 ? 'table-success' : '' }}">
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <strong>{{ $enroll->program->title ?? 'N/A' }}</strong><br>
                                            <small class="text-muted">{{ $enroll->program->code ?? '' }}</small>
                                        </td>
                                        <td>{{ $enroll->session->title ?? 'N/A' }}</td>
                                        <td>{{ $enroll->semester->title ?? 'N/A' }}</td>
                                        <td>{{ $enroll->section->title ?? 'N/A' }}</td>
                                        <td>
                                            @if($enroll->status == 1)
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> {{ __('active') }}
                                            </span>
                                            @else
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-times-circle"></i> {{ __('inactive') }}
                                            </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($enroll->created_at))
                                            {{ date('d M Y', strtotime($enroll->created_at)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> {{ __('no_enrollments_found') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <!-- [ Student Profile ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
