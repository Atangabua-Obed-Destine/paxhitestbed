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
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route .'.student-attendance') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="student">{{ __('field_student') }} <span>*</span></label>
                                    <select class="form-control select2" name="student" id="student" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($students as $student)
                                        <option value="{{ $student->id }}" @if($selected_student == $student->id) selected @endif>{{ $student->student_id }} - {{ $student->first_name }} {{ $student->last_name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_student') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="session">{{ __('field_session') }} <span>*</span></label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach( $sessions as $session )
                                        <option value="{{ $session->id }}" @if($selected_session == $session->id) selected @endif>{{ $session->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_session') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if(isset($attendances) && isset($studentEnroll))
                    <div class="card-header">
                        <p>{{ __('attendance_present') }}: <span class="text-primary">{{ __('P') }}</span> | {{ __('attendance_absent') }}: <span class="text-danger">{{ __('A') }}</span> | {{ __('attendance_leave') }}: <span class="text-success">{{ __('L') }}</span> | {{ __('attendance_holiday') }}: <span class="text-warning">{{ __('H') }}</span></p>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="table table-attendance table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_subject') }}</th>
                                        <th>{{ __('field_code') }}</th>
                                        <th>{{ __('field_period') }}</th>
                                        <th>{{ __('P') }}</th>
                                        <th>{{ __('A') }}</th>
                                        <th>{{ __('L') }}</th>
                                        <th>{{ __('H') }}</th>
                                        <th>{{ __('%') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @php
                                    $grand_total_working = 0;
                                    $grand_total_present = 0;
                                    $grand_total_absent = 0;
                                    $grand_total_leave = 0;
                                    $grand_total_holiday = 0;
                                  @endphp
                                  @foreach( $studentEnroll->subjects as $key => $row )
                                    <tr>
                                        <td>{{ $row->title }}</td>
                                        <td>{{ $row->code }}</td>
                                        @php
                                            $total_present = 0;
                                            $total_absent = 0;
                                            $total_leave = 0;
                                            $total_holiday = 0;
                                        @endphp
                                        @if(isset($attendances))
                                        @foreach($attendances as $user_attend)
                                        @if($user_attend->subject_id == $row->id)
                                            @if($user_attend->attendance == 1)
                                            @php
                                                $total_present = $total_present + 1;
                                            @endphp
                                            @elseif($user_attend->attendance == 2)
                                            @php
                                                $total_absent = $total_absent + 1;
                                            @endphp
                                            @elseif($user_attend->attendance == 3)
                                            @php
                                                $total_leave = $total_leave + 1;
                                            @endphp
                                            @elseif($user_attend->attendance == 4)
                                            @php
                                                $total_holiday = $total_holiday + 1;
                                            @endphp
                                            @endif
                                        @endif
                                        @endforeach
                                        @endif
                                        @php
                                            $total_working_days = $total_present + $total_absent + $total_leave;
                                        @endphp
                                        <td>{{ $total_working_days }}</td>
                                        <td>{{ $total_present }}</td>
                                        <td>{{ $total_absent }}</td>
                                        <td>{{ $total_leave }}</td>
                                        <td>{{ $total_holiday }}</td>
                                        @php
                                            $grand_total_working = $grand_total_working + $total_working_days;
                                            $grand_total_present = $grand_total_present + $total_present;
                                            $grand_total_absent = $grand_total_absent + $total_absent;
                                            $grand_total_leave = $grand_total_leave + $total_leave;
                                            $grand_total_holiday = $grand_total_holiday + $total_holiday;
                                            if($total_working_days == 0){
                                                $total_working_days = 1;
                                            }
                                        @endphp
                                        <td>{{ round((($total_present / $total_working_days) * 100), 2) }} %</td>
                                    </tr>
                                  @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th></th>
                                        <th>{{ __('field_grand_total') }}</th>
                                        <th>{{ $grand_total_working }}</th>
                                        <th>{{ $grand_total_present }}</th>
                                        <th>{{ $grand_total_absent }}</th>
                                        <th>{{ $grand_total_leave }}</th>
                                        <th>{{ $grand_total_holiday }}</th>
                                        @php
                                            if($grand_total_working == 0){
                                                $grand_total_working = 1;
                                            }
                                        @endphp
                                        <th>{{ round((($grand_total_present / $grand_total_working) * 100), 2) }} %</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection