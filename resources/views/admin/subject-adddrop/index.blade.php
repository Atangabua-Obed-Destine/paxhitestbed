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
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="student">{{ __('field_matricule') }} / {{ __('field_student_id') }} <span>*</span></label>
                                    <select class="form-control select2" name="student" id="student" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($students as $enrollment)
                                        <option value="{{ $enrollment->matricule ?? $enrollment->student->student_id }}" 
                                                @if($selected_student == ($enrollment->matricule ?? $enrollment->student->student_id)) selected @endif>
                                            {{ $enrollment->matricule ?? $enrollment->student->student_id }} - 
                                            {{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }}
                                            @if($enrollment->program)
                                                <span class="badge bg-info">{{ $enrollment->program->shortcode }}</span>
                                            @endif
                                        </option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_matricule') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-block">
                        @if(isset($row))
                        @php
                            // Use the selected enrollment from controller
                            $enroll = $curr_enr ?? \App\Models\Student::enroll($row->id);
                            // Get the program_id for filtering (from selected enrollment or current)
                            $filterProgramId = isset($selected_program_id) ? $selected_program_id : $row->program_id;
                        @endphp

                        @php
                            $total_credits = 0;
                            $total_cgpa = 0;
                        @endphp
                        @foreach( $row->studentEnrolls as $key => $item )
                        @if($item->program_id == $filterProgramId)

                            @if(isset($item->subjectMarks))
                            @foreach($item->subjectMarks as $mark)

                                @php
                                $marks_per = round($mark->total_marks);
                                @endphp

                                @foreach($grades as $grade)
                                @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                                @php
                                // Always count all courses towards CGPA calculation, including F grades
                                $total_cgpa = $total_cgpa + ($grade->point * $mark->subject->credit_hour);
                                $total_credits = $total_credits + $mark->subject->credit_hour;
                                @endphp
                                @break
                                @endif
                                @endforeach

                            @endforeach
                            @endif
                        
                        @endif
                        @endforeach
                        
                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="row gx-2 scheduler-border">
                                    <legend>{{ __('tab_basic_info') }}</legend>
                                    @php
                                        // Use the selected enrollment from controller
                                        $displayEnroll = $curr_enr ?? \App\Models\StudentEnroll::where('student_id', $row->id)
                                            ->with('program')->orderBy('id', 'desc')->first();
                                    @endphp
                                    <p><mark class="text-primary">{{ __('field_matricule') }}:</mark> 
                                        <strong style="font-size: 15px; color: #667eea;">#{{ $displayEnroll->matricule ?? $row->student_id }}</strong>
                                        @if($displayEnroll && $displayEnroll->program)
                                            <span class="badge" style="background: {{ $displayEnroll->program->academic_level == 'M' ? '#f5576c' : ($displayEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px; margin-left: 5px;">
                                                {{ $displayEnroll->program->academic_level == 'A' ? 'UG' : ($displayEnroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                            </span>
                                        @endif
                                    </p>
                                    <hr/>

                                    <p><mark class="text-primary">{{ __('field_name') }}:</mark> {{ $row->first_name }} {{ $row->last_name }}</p>
                                    <hr/>

                                    <p><mark class="text-primary">{{ __('field_gender') }}:</mark> 
                                        @if( $row->gender == 1 )
                                        {{ __('gender_male') }}
                                        @elseif( $row->gender == 2 )
                                        {{ __('gender_female') }}
                                        @elseif( $row->gender == 3 )
                                        {{ __('gender_other') }}
                                        @endif
                                    </p><hr/>

                                    <p><mark class="text-primary">{{ __('field_total_credit_hour') }}:</mark> {{ round($total_credits, 2) }}</p>
                                    <hr/>

                                    <p><mark class="text-primary">{{ __('field_cumulative_gpa') }}:</mark> 
                                        @php
                                        if($total_credits <= 0){
                                            $total_credits = 1;
                                        }
                                        $com_gpa = $total_cgpa / $total_credits;
                                        echo number_format((float)$com_gpa, 2, '.', '');
                                        @endphp
                                    </p>
                                    <hr/>
                                </fieldset>
                            </div>
                            <div class="col-md-6">
                                <fieldset class="row gx-2 scheduler-border">
                                    <legend>{{ __('field_academic_information') }}</legend>
                                    <p><mark class="text-primary">{{ __('field_batch') }}:</mark> {{ $row->batch->title ?? '' }}</p><hr/>

                                    <p><mark class="text-primary">{{ __('field_program') }}:</mark> {{ $enroll->program->title ?? $row->program->title ?? '' }}</p><hr/>

                                    <p><mark class="text-primary">{{ __('field_session') }}:</mark> {{ $enroll->session->title ?? '' }}</p><hr/>

                                    <p><mark class="text-primary">{{ __('field_semester') }}:</mark> {{ $enroll->semester->title ?? '' }}</p><hr/>

                                    <p><mark class="text-primary">{{ __('field_section') }}:</mark> {{ $enroll->section->title ?? '' }}</p><hr/>
                                </fieldset>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>


                @if(isset($row))  
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('status_current') }} {{ __('field_session') }}: {{ $enroll->session->title ?? '' }} | {{ $enroll->semester->title ?? '' }} | {{ $enroll->section->title ?? '' }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_code') }}</th>
                                        <th>{{ __('field_subject') }}</th>
                                        <th>{{ __('field_credit_hour') }}</th>
                                        <th>{{ __('field_point') }}</th>
                                        <th>{{ __('field_grade') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $semester_credits = 0;
                                        $semester_cgpa = 0;
                                    @endphp

                                    @isset($enroll->subjects)
                                    @foreach( $enroll->subjects as $subject )
                                    @php
                                        $semester_credits = $semester_credits + $subject->credit_hour;
                                        $subject_grade = null;
                                    @endphp
                                    
                                    <tr>
                                        <td>{{ $subject->code }}</td>
                                        <td>
                                            {{ $subject->title }}
                                            @if($subject->subject_type == 0)
                                             ({{ __('subject_type_optional') }})
                                            @elseif($subject->subject_type == 2)
                                             ({{ __('subject_type_university_requirement') }})
                                            @endif
                                        </td>
                                        <td>{{ round($subject->credit_hour, 2) }}</td>
                                        <td>
                                            @if(isset($enroll->subjectMarks))
                                            @foreach($enroll->subjectMarks as $mark)
                                                @if($mark->subject_id == $subject->id)
                                                @php
                                                $marks_per = round($mark->total_marks);
                                                @endphp

                                                @foreach($grades as $grade)
                                                @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                                                {{ number_format((float)$grade->point * $subject->credit_hour, 2, '.', '') }}
                                                @php
                                                $semester_cgpa = $semester_cgpa + ($grade->point * $subject->credit_hour);
                                                $subject_grade = $grade->title;
                                                @endphp
                                                @break
                                                @endif
                                                @endforeach

                                                @endif
                                            @endforeach
                                            @endif
                                        </td>
                                        <td>{{ $subject_grade ?? '' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-drop-subject" 
                                                    data-subject="{{ $subject->id }}" 
                                                    data-title="{{ $subject->code }} - {{ $subject->title }}">
                                                <i class="fas fa-minus-circle"></i> {{ __('Drop') }}
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                    @endisset
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">{{ __('field_term_total') }}</th>
                                        <th>{{ $semester_credits }}</th>
                                        <th>{{ number_format((float)$semester_cgpa, 2, '.', '') }}</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>


                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('field_assign') }} {{ __('field_subject') }}</h5>
                    </div>
                    <div class="card-block">
                        @if(isset($maxCreditLimit) && $maxCreditLimit !== null)
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i> Maximum allowed credits for this semester: <strong>{{ $maxCreditLimit }} Credits</strong>
                            </div>
                        @endif
                        
                        @php
                            // Get IDs of currently enrolled subjects
                            $enrolledSubjectIds = $curr_enr->subjects->pluck('id')->toArray();
                            // Filter subjects to show only those NOT currently enrolled
                            $availableSubjects = $subjects->filter(function($subject) use ($enrolledSubjectIds) {
                                return !in_array($subject->id, $enrolledSubjectIds);
                            });
                        @endphp
                        
                        @if($availableSubjects->isEmpty())
                            <div class="alert alert-info mb-0" role="alert">
                                {{ __('No additional subjects are available for enrollment.') }}
                            </div>
                        @else
                            <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="row">

                                    <input type="text" name="student" value="{{ $row->id }}" hidden>
                                    <input type="hidden" name="enrollment_id" value="{{ $enroll->id ?? '' }}">
                                    <input type="hidden" name="matricule" value="{{ $enroll->matricule ?? '' }}">

                                    <div class="form-group col-md-12">
                                        <label for="subject">{{ __('field_subject') }} <span>* ({{ __('select_multiple') }})</span></label>
                                        <select class="form-control select2" name="subjects[]" id="subject" multiple required>
                                            @foreach( $availableSubjects as $subject )
                                            <option value="{{ $subject->id }}">
                                                {{ $subject->code }} - {{ $subject->title }} ({{ $subject->credit_hour }} {{ __('field_credit_hour') }})
                                            </option>
                                            @endforeach
                                        </select>

                                        <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_subject') }}
                                        </div>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                            <i class="fas fa-check"></i> {{ __('btn_update') }}
                                        </button>
                                        <!-- Include Confirm modal -->
                                        @include($view.'.confirm')
                                    </div>
                                    
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
                
                <!-- Hidden form for dropping subjects -->
                <form method="post" action="{{ route($route.'.drop') }}" id="drop-subject-form">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $row->id }}">
                    <input type="hidden" name="enrollment_id" value="{{ $enroll->id ?? '' }}">
                    <input type="hidden" name="subject_id" id="drop-subject-id" value="">
                </form>
                @include($view.'.drop-confirm')
                @endif

            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";

$(document).ready(function() {
    // Handle drop subject button click
    $('.btn-drop-subject').on('click', function(e){
        e.preventDefault();
        const subjectId = $(this).data('subject');
        const subjectTitle = $(this).data('title');
        $('#drop-subject-id').val(subjectId);
        $('#drop-subject-label').text(subjectTitle);
        $('#dropConfirmModal').modal('show');
    });

    // Handle confirm drop button
    $('#confirm-drop-button').on('click', function(){
        if(!$('#drop-subject-id').val()){
            return;
        }
        $('#drop-subject-form').trigger('submit');
    });

    // Clear form when modal is hidden
    $('#dropConfirmModal').on('hidden.bs.modal', function(){
        $('#drop-subject-id').val('');
        $('#drop-subject-label').text('');
    });
});
</script>
@endsection