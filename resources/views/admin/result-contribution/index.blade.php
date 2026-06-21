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
                        <span>Configure mark distribution per course - Total contribution must equal 100%</span>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
                                    <select class="form-control faculty" name="faculty" id="faculty" required>
                                        <option value="0">{{ __('select') }}</option>
                                        @foreach( $faculties as $faculty )
                                        <option value="{{ $faculty->id }}" @if( $selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_faculty') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="program">{{ __('field_program') }} <span>*</span></label>
                                    <select class="form-control program" name="program" id="program" required>
                                        <option value="0">{{ __('select') }}</option>
                                        @foreach( $programs as $program )
                                        <option value="{{ $program->id }}" @if( $selected_program == $program->id) selected @endif>{{ $program->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_program') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="subject">{{ __('field_course') }} <span>*</span></label>
                                    <select class="form-control subject" name="subject" id="subject" required>
                                        <option value="0">{{ __('select') }}</option>
                                        @foreach( $subjects as $subject )
                                        <option value="{{ $subject->id }}" @if( $selected_subject == $subject->id) selected @endif>{{ $subject->code }} - {{ $subject->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_course') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(isset($subject_config_status) && count($subject_config_status) > 0)
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5><i class="fas fa-clipboard-check"></i> Configuration Status</h5>
                            <span>
                                <span class="badge badge-success">{{ $configured_count }}</span> Configured
                                @if($unconfigured_count > 0)
                                    &nbsp;|&nbsp;
                                    <span class="badge badge-danger">{{ $unconfigured_count }}</span> Not Configured
                                @endif
                                &nbsp;|&nbsp;
                                <span class="badge badge-info">{{ count($subject_config_status) }}</span> Total Courses
                            </span>
                        </div>
                        @if($unconfigured_count > 0)
                        <span class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> {{ $unconfigured_count }} course(s) need mark distribution setup</span>
                        @else
                        <span class="text-success font-weight-bold"><i class="fas fa-check-circle"></i> All courses configured</span>
                        @endif
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 12%;">Code</th>
                                        <th>Course Title</th>
                                        <th style="width: 15%;" class="text-center">Status</th>
                                        <th style="width: 10%;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subject_config_status as $idx => $scs)
                                    <tr class="{{ !$scs['configured'] ? 'table-warning' : '' }}">
                                        <td>{{ $idx + 1 }}</td>
                                        <td><strong>{{ $scs['code'] }}</strong></td>
                                        <td>{{ $scs['title'] }}</td>
                                        <td class="text-center">
                                            @if($scs['configured'])
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Configured</span>
                                            @else
                                                <span class="badge badge-danger"><i class="fas fa-times"></i> Not Configured</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route($route.'.index', ['faculty' => $selected_faculty, 'program' => $selected_program, 'subject' => $scs['id']]) }}" class="btn btn-sm {{ $scs['configured'] ? 'btn-outline-info' : 'btn-warning' }}">
                                                <i class="fas fa-{{ $scs['configured'] ? 'edit' : 'cog' }}"></i>
                                                {{ $scs['configured'] ? 'Edit' : 'Configure' }}
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($row) || $selected_subject != '0')
            <div class="col-md-12 col-lg-12">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5>Mark Distribution Configuration</h5>
                        <span>Total contribution of final result must be equal to 100%</span>
                    </div>
                    <div class="card-block row">
                        
                        <!-- Form Start -->
                        <input name="id" type="hidden" value="{{ (isset($row)) ? $row->id : '-1' }}">
                        <input name="subject" type="hidden" value="{{ $selected_subject }}">

                        @foreach($exams as $key => $exam)
                        <input type="text" name="exams[]" value="{{ $exam->id }}" hidden>

                        <div class="form-group col-md-4">
                            <label for="exam-{{ $key }}">{{ $exam->title }} (%) <span>*</span></label>
                            <input type="text" class="form-control" name="contributions[]" id="exam-{{ $key }}" value="{{ isset($exam_contributions[$exam->id]) ? round($exam_contributions[$exam->id]->contribution, 2) : '0' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ $exam->title }}
                            </div>
                        </div>
                        @endforeach

                        <div class="form-group col-md-4">
                            <label for="attendances">{{ __('field_attendance') }} (%) <span>*</span></label>
                            <input type="text" class="form-control" name="attendances" id="attendances" value="{{ isset($row->attendances)?round($row->attendances, 2):'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_attendance') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="assignments">{{ __('field_assignment') }} (%) <span>*</span></label>
                            <input type="text" class="form-control" name="assignments" id="assignments" value="{{ isset($row->assignments)?round($row->assignments, 2):'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_assignment') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="activities">{{ __('field_activities') }} (%) <span>*</span></label>
                            <input type="text" class="form-control" name="activities" id="activities" value="{{ isset($row->activities)?round($row->activities, 2):'' }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_activities') }}
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">@isset($row) <i class="fas fa-check"></i> {{ __('btn_update') }} @else <i class="fas fa-check"></i> {{ __('btn_save') }} @endif</button>
                    </div>

                </div>
                </form>
            </div>
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
// Ajax List
$(".faculty").on('change',function(e){
    e.preventDefault(e);
    var faculty=$('.faculty').val();
    $.ajax({
        type:'POST',
        url: "{{ route('filter-program') }}",
        data:{
            _token  : "{{ csrf_token() }}",
            faculty : faculty
        },
        success:function(data){
            // FilterController returns array directly, not {success, data} format
            $('.program').html('<option value="0">{{ __("select") }}</option>');
            if(data && data.length > 0){
                $.each(data,function(key, value){
                    $('.program').append('<option value="'+ value.id +'">'+ value.title +'</option>');
                });
            }
            $('.subject').html('<option value="0">{{ __("select") }}</option>');
        }
    });
});

$(".program").on('change',function(e){
    e.preventDefault(e);
    var program=$('.program').val();
    $.ajax({
        type:'POST',
        url: "{{ route('filter-subject') }}",
        data:{
            _token  : "{{ csrf_token() }}",
            program : program
        },
        success:function(data){
            // FilterController returns array directly, not {success, data} format
            $('.subject').html('<option value="0">{{ __("select") }}</option>');
            if(data && data.length > 0){
                $.each(data,function(key, value){
                    $('.subject').append('<option value="'+ value.id +'">'+ value.code +' - '+ value.title +'</option>');
                });
            }
        }
    });
});
</script>
@endsection