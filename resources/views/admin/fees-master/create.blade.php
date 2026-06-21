@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            @if(isset($statistics))
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#statisticsCollapse">
                        <h5 class="d-inline-block">
                            <i class="fas fa-chart-bar"></i> {{ __('Fee Assignment Statistics') }}
                        </h5>
                        @if($statistics['students_without_fees'] > 0)
                            <span class="badge badge-danger float-end">
                                <i class="fas fa-exclamation-triangle"></i> {{ $statistics['students_without_fees'] }} {{ __('Students Without Fees') }}
                            </span>
                        @else
                            <span class="badge badge-success float-end">
                                <i class="fas fa-check-circle"></i> {{ __('All Students Assigned') }}
                            </span>
                        @endif
                        <i class="fas fa-chevron-down float-end me-2"></i>
                    </div>
                    <div id="statisticsCollapse" class="collapse show">
                        <div class="card-block">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white mb-0">
                                        <div class="card-block">
                                            <h6 class="text-white">{{ __('Total Students') }}</h6>
                                            <h3 class="text-white">{{ $statistics['total_students'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white mb-0">
                                        <div class="card-block">
                                            <h6 class="text-white">{{ __('With Installment Fees') }}</h6>
                                            <h3 class="text-white">{{ $statistics['students_with_fees'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-danger text-white mb-0">
                                        <div class="card-block">
                                            <h6 class="text-white">{{ __('Without Installment Fees') }}</h6>
                                            <h3 class="text-white">{{ $statistics['students_without_fees'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white mb-0">
                                        <div class="card-block">
                                            <h6 class="text-white">{{ __('Total Amount Assigned') }}</h6>
                                            <h3 class="text-white">{{ number_format($statistics['total_fees_assigned']) }} XAF</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if(count($statistics['fees_by_category']) > 0)
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6><i class="fas fa-list"></i> {{ __('Fees by Category') }}</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('Category') }}</th>
                                                    <th>{{ __('Students Assigned') }}</th>
                                                    <th>{{ __('Total Amount') }}</th>
                                                    <th>{{ __('Average Amount') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($statistics['fees_by_category'] as $category => $data)
                                                <tr>
                                                    <td><span class="badge badge-info">{{ $category }}</span></td>
                                                    <td>{{ $data['count'] }}</td>
                                                    <td>{{ number_format($data['amount']) }} XAF</td>
                                                    <td>{{ number_format($data['amount'] / $data['count']) }} XAF</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.create') }}">
                            <div class="row gx-2">
                                @include('common.inc.fees_search_filter')

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($rows))
            @if(count($rows) > 0)
            <div class="col-sm-12">
                <form action="{{ route($route.'.store') }}" class="needs-validation" novalidate method="post">
                @csrf
                <div class="card">
                    <div class="card-block">
                        <input type="text" name="faculty" value="{{ $selected_faculty }}" hidden>
                        <input type="text" name="program" value="{{ $selected_program }}" hidden>
                        <input type="text" name="session" value="{{ $selected_session }}" hidden>
                        <input type="text" name="semester" value="{{ $selected_semester }}" hidden>
                        <input type="text" name="section" value="{{ $selected_section }}" hidden>


                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox" class="all_select" checked>
                                                <label for="checkbox" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>{{ __('field_student_id') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_credit_hour_short') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    @php
                                        // Check if student already has installment fees assigned for this program/session/semester
                                        $existingFees = $row->fees->filter(function($fee) {
                                            return $fee->category && 
                                                   ($fee->category->is_first_installment == 1 || 
                                                    $fee->category->is_second_installment == 1);
                                        });
                                        $hasExistingFees = $existingFees->count() > 0;
                                    @endphp
                                    <tr class="{{ $hasExistingFees ? 'table-warning' : '' }}">
                                        <td>
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" name="students[]" id="checkbox-{{ $row->id }}" value="{{ $row->id }}" 
                                                    {{ $hasExistingFees ? 'disabled' : 'checked' }}>
                                                <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.student.show', $row->student->id) }}">
                                            #{{ $row->student->student_id ?? '' }}
                                            </a>
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>
                                            @php
                                                $total_credits = 0;
                                                foreach($row->subjects as $subject){
                                                    $total_credits = $total_credits + $subject->credit_hour;
                                                }
                                            @endphp
                                            {{ $total_credits }}
                                        </td>
                                        <td>{{ $row->program->shortcode ?? '' }}</td>
                                        <td>{{ $row->session->title ?? '' }}</td>
                                        <td>{{ $row->semester->title ?? '' }}</td>
                                        <td>{{ $row->section->title ?? '' }}</td>
                                        <td>
                                            @if($hasExistingFees)
                                                @foreach($existingFees as $fee)
                                                    <div class="mb-1">
                                                        <span class="badge badge-info">{{ $fee->category->title }}</span>
                                                        <span class="badge badge-success">{{ $fee->fee_amount }} XAF</span>
                                                    </div>
                                                @endforeach
                                                <small class="text-muted">Already Assigned</small>
                                            @else
                                                <span class="badge badge-secondary">Not Assigned</span>
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

                <div class="card">
                    <div class="card-block">
                        <div class="row">
                          <div class="form-group col-md-4">
                            <label for="category">{{ __('field_fees_type') }} <span>*</span></label>
                            <select class="form-control" name="category" id="category" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach( $categories as $category )
                                <option value="{{ $category->id }}" @if(old('category') == $category->id) selected @endif>{{ $category->title }}</option>
                                @endforeach
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_fees_type') }}
                            </div>
                          </div>

                          <div class="form-group col-md-4">
                            <label for="assign_date" class="form-label">{{ __('field_assign') }} {{ __('field_date') }} <span>*</span></label>
                            <input type="date" class="form-control" name="assign_date" id="assign_date" value="{{ date('Y-m-d') }}" readonly required>

                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_assign') }} {{ __('field_date') }}
                            </div>
                          </div>

                          <div class="form-group col-md-4">
                            <label for="due_date" class="form-label">{{ __('field_due_date') }} <span>*</span></label>
                            <input type="date" class="form-control date" name="due_date" id="due_date" value="{{ date('Y-m-d') }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_due_date') }}
                            </div>
                          </div>

                          <div class="form-group col-md-4">
                            <label for="amount" class="form-label">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                            <input type="text" class="form-control autonumber" name="amount" id="amount" value="{{ old('amount') }}" required>

                            <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_amount') }}
                            </div>
                          </div>

                          <div class="form-group col-md-6">
                            <label>{{ __('field_amount_type') }}</label><br/>
                            <div class="radio d-inline">
                                <input type="radio" name="type" id="type_fixed" value="1" @if( old('type') == null ) checked @elseif( old('type') == 1 )  checked @endif>
                                <label for="type_fixed" class="cr">{{ __('amount_type_fixed') }}</label>
                            </div>
                            <div class="radio d-inline">
                                <input type="radio" name="type" id="type_per_credit" value="2" @if( old('type') == 2 ) checked @endif>
                                <label for="type_per_credit" class="cr">{{ __('amount_type_per_credit') }}</label>
                            </div>
                          </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal">
                            <i class="fas fa-check"></i> {{ __('btn_assign') }}
                        </button>
                        <!-- Include Confirm modal -->
                        @include($view.'.confirm')
                    </div>
                </div>
                </form>
            </div>
            @endif
            @endif

        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";
// checkbox all-check-button selector
$(".all_select").on('click',function(e){
    if($(this).is(":checked")){
        // check all checkbox except disabled ones
        $("input:checkbox:not(:disabled)").prop('checked', true);
    }
    else if($(this).is(":not(:checked)")){
        // uncheck all checkbox except disabled ones
        $("input:checkbox:not(:disabled)").prop('checked', false);
    }
});
</script>
@endsection