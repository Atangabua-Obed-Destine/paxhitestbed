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


                    @if(isset($row))
                    <div class="card-block">
                        @php
                            // Use selected enrollment from controller if available, otherwise get default
                            $enroll = $selectedEnrollment ?? \App\Models\Student::enroll($row->id);
                        @endphp

                        @php
                            $total_credits = 0;
                            $total_cgpa = 0;
                            $cgpa_matricule = $enroll->matricule ?? null;
                            $cgpa_program_id = $enroll->program_id ?? null;
                        @endphp
                        @foreach( $row->studentEnrolls as $key => $item )
                            @if($cgpa_matricule && $item->matricule != $cgpa_matricule)
                                @continue
                            @endif
                            @if($cgpa_program_id && $item->program_id != $cgpa_program_id)
                                @continue
                            @endif

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

                        @endforeach

                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="row gx-2 scheduler-border">
                                    <legend>{{ __('tab_basic_info') }}</legend>
                                    <p><mark class="text-primary">{{ __('field_student_id') }} (Internal):</mark> #{{ $row->student_id }}</p>
                                    <hr/>
                                    @php
                                        // Use the selectedEnrollment from controller, or fall back to latest
                                        $displayEnroll = $selectedEnrollment ?? $latestStudentEnroll ?? \App\Models\StudentEnroll::where('student_id', $row->id)
                                            ->with('program')
                                            ->orderBy('id', 'desc')
                                            ->first();
                                    @endphp
                                    @if($displayEnroll)
                                    <p><mark class="text-primary">{{ __('field_matricule') }} (Current):</mark> 
                                        <strong style="font-size: 16px; color: #667eea;">#{{ $displayEnroll->matricule }}</strong>
                                        @if($displayEnroll->program)
                                            <span class="badge" style="background: {{ $displayEnroll->program->academic_level == 'M' ? '#f5576c' : ($displayEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 10px; margin-left: 5px;">
                                                {{ $displayEnroll->program->academic_level == 'A' ? 'Undergraduate' : ($displayEnroll->program->academic_level == 'M' ? 'Masters' : 'Doctoral') }}
                                            </span>
                                        @endif
                                    </p>
                                    <hr/>
                                    @endif

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
                    </div>
                    @endif
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
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>

                
                <form action="{{ route($route.'.store') }}" method="post" id="enrollmentForm">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('field_next_enrollment') }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- Program Change Warning Alert -->
                        <div id="programChangeAlert" class="alert alert-warning" style="display: none;">
                            <h6><i class="fas fa-exclamation-triangle"></i> <strong>Program Change Detected</strong></h6>
                            <p class="mb-0">You are changing the student's program. This will create a NEW enrollment with important implications.</p>
                        </div>
                        
                        <!-- NEW MATRICULE PREVIEW -->
                        <div id="newMatriculePreview" class="alert alert-info border-primary" style="display: none; border-width: 3px !important;">
                            <h5 class="alert-heading">
                                <i class="fas fa-id-card text-primary"></i> 
                                <strong>Student Matricule Information</strong>
                            </h5>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Current Matricule:</strong><br>
                                        <span class="badge" style="background: #667eea; font-size: 16px; padding: 8px 12px;">
                                            {{ $displayEnroll->matricule ?? $latestStudentEnroll->matricule ?? $row->student_id }}
                                        </span>
                                        @php $enrollProgram = $displayEnroll->program ?? ($latestStudentEnroll->program ?? null); @endphp
                                        @if($enrollProgram)
                                        <span class="badge" style="background: {{ $enrollProgram->academic_level == 'M' ? '#f5576c' : ($enrollProgram->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; font-size: 12px; margin-left: 5px;">
                                            {{ $enrollProgram->academic_level == 'A' ? 'Undergraduate' : ($enrollProgram->academic_level == 'M' ? 'Masters' : 'Doctoral') }}
                                        </span>
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-0" id="newMatriculeDisplay" style="display: none;">
                                        <strong>New Matricule Will Be:</strong><br>
                                        <span class="badge bg-success" style="font-size: 16px; padding: 8px 12px;" id="newMatriculeBadge">
                                            Calculating...
                                        </span>
                                        <span class="badge" style="font-size: 12px; margin-left: 5px;" id="newLevelBadge"></span>
                                        <br>
                                        <small class="text-success mt-2 d-block">
                                            <i class="fas fa-check-circle"></i> This is a NEW enrollment, not an update
                                        </small>
                                    </p>
                                    <p class="mb-0" id="sameMatriculeDisplay" style="display: none;">
                                        <strong>Matricule Status:</strong><br>
                                        <span class="badge bg-info" style="font-size: 14px; padding: 8px 12px;">
                                            <i class="fas fa-info-circle"></i> Current matricule will be retained
                                        </span>
                                        <br>
                                        <small class="text-muted mt-2 d-block">
                                            Transfer within same academic level
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dynamic Validation Warnings -->
                        <div id="validationWarnings" style="display: none;"></div>
                        
                        <div class="row gx-2">
                            <input type="text" name="student" value="{{ $row->id }}" hidden>
                            <input type="hidden" name="is_program_change" id="is_program_change" value="0">

                            <div class="form-group col-md-3">
                                <label for="program">{{ __('field_program') }} <span>*</span></label>
                                <select class="form-control" name="program" id="program" required>
                                  <option value="">{{ __('select') }}</option>
                                  @foreach( $programs as $program )
                                  <option value="{{ $program->id }}" @if( ($targetProgramId ?? $row->program_id) == $program->id) selected @endif>{{ $program->title }}</option>
                                  @endforeach
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_program') }}
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="session">{{ __('field_session') }} <span>*</span></label>
                                <select class="form-control" name="session" id="session" required>
                                  <option value="">{{ __('select') }}</option>
                                  @foreach( $sessions as $session )
                                  <option value="{{ $session->id }}" @if( $enroll->session_id == $session->id) selected @endif>{{ $session->title }}</option>
                                  @endforeach
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_session') }}
                                </div>
                            </div>
                                                        <div class="form-group col-md-3">
                                                                <label for="semester_year">{{ __('field_year') }} <span>*</span></label>
                                                                <select class="form-control" name="semester_year" id="semester_year" required>
                                                                    <option value="">{{ __('select') }}</option>
                                                                    @foreach($semesterOptions ?? [] as $year => $items)
                                                                        <option value="{{ $year }}" @if(optional($enroll->semester)->year == $year) selected @endif>{{ __('field_year') }} {{ $year }}</option>
                                                                    @endforeach
                                                                </select>

                                                                <div class="invalid-feedback">
                                                                    {{ __('required_field') }} {{ __('field_year') }}
                                                                </div>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                                <label for="semester">{{ __('field_semester') }} <span>*</span></label>
                                                                <select class="form-control next_semester" name="semester" id="semester" required>
                                                                    <option value="">{{ __('select') }}</option>
                                                                </select>

                                                                <div class="invalid-feedback">
                                                                    {{ __('required_field') }} {{ __('field_semester') }}
                                                                </div>
                                                        </div>
                            <div class="form-group col-md-3">
                                <label for="section">{{ __('field_section') }} <span>*</span></label>
                                <select class="form-control next_section" name="section" id="section" required>
                                  <option value="">{{ __('select') }}</option>
                                  @foreach( $sections as $section )
                                  <option value="{{ $section->id }}" @if( $enroll->section_id == $section->id) selected @endif>{{ $section->title }}</option>
                                  @endforeach
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_section') }}
                                </div>
                            </div>
                            <div class="form-group col-md-12">
                                <label for="subject">{{ __('field_subject') }} <span>* ({{ __('select_multiple') }})</span></label>
                                
                                <!-- Subject Selection Enhancement -->
                                <div class="subject-selection-container">
                                    <!-- Search and Actions Bar -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="subjectSearch" placeholder="Search subjects by code or name...">
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button type="button" class="btn btn-sm btn-primary" id="selectAllSubjects">
                                                <i class="fas fa-check-double"></i> Select All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary" id="clearAllSubjects">
                                                <i class="fas fa-times"></i> Clear All
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Statistics Display -->
                                    <div class="alert alert-info mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Selected:</strong> <span id="selectedCount">0</span> subject(s) | 
                                                <strong>Total Credits:</strong> <span id="totalCredits">0</span>
                                            </div>
                                            @if(isset($maxCreditLimit) && $maxCreditLimit !== null)
                                            <div>
                                                <strong class="text-danger">Max Limit:</strong> 
                                                <span class="badge bg-danger" style="font-size: 1rem;">{{ $maxCreditLimit }} Credits</span>
                                            </div>
                                            @endif
                                        </div>
                                        <div id="creditWarning" class="mt-2" style="display: none;">
                                            <small class="text-danger">
                                                <i class="fas fa-exclamation-triangle"></i> 
                                                <strong>Warning:</strong> Selected credits exceed the maximum limit!
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Subject Cards Grid -->
                                    <div class="subject-list" id="subjectList">
                                        @foreach( $subjects as $subject )
                                        <div class="subject-item form-check border rounded p-3 mb-2" 
                                             data-subject-code="{{ strtolower($subject->code) }}" 
                                             data-subject-title="{{ strtolower($subject->title) }}"
                                             data-credit-hour="{{ $subject->credit_hour }}">
                                            <input class="form-check-input subject-checkbox" 
                                                   type="checkbox" 
                                                   name="subjects[]" 
                                                   value="{{ $subject->id }}" 
                                                   id="subject_{{ $subject->id }}">
                                            <label class="form-check-label w-100" for="subject_{{ $subject->id }}" style="cursor: pointer;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>{{ $subject->code }}</strong> - {{ $subject->title }}
                                                        <div class="mt-1">
                                                            <span class="badge bg-info text-white">{{ $subject->credit_hour }} Credits</span>
                                                            @if($subject->subject_type == 1)
                                                                <span class="badge bg-danger text-white">Compulsory</span>
                                                            @else
                                                                <span class="badge bg-success text-white">Optional</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <i class="fas fa-check-circle text-success" style="font-size: 1.5rem; display: none;"></i>
                                                </div>
                                            </label>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_subject') }}
                                </div>
                            </div>
                            
                            <!-- Program Change Reason (shown only when program changes) -->
                            <div class="form-group col-md-12" id="programChangeReasonContainer" style="display: none;">
                                <label for="program_change_reason">{{ __('Program Change Reason') }} <span>*</span></label>
                                <textarea class="form-control" name="program_change_reason" id="program_change_reason" rows="3" 
                                    placeholder="Please provide a detailed reason for changing the student's program..."></textarea>
                                <small class="form-text text-muted">
                                    This information will be logged for audit purposes. Please explain why the program change is necessary.
                                </small>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('Program Change Reason') }}
                                </div>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <button type="button" class="btn btn-success" id="enrollButton">
                                    <i class="fas fa-exchange"></i> {{ __('btn_enroll') }}
                                </button>
                                <!-- Include Confirm modal -->
                                @include($view.'.confirm')
                            </div>
                        </div>
                    </div>
                </div>
                </form>
                @endif

            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_css')
<style>
    .subject-selection-container {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .subject-list {
        max-height: 500px;
        overflow-y: auto;
        padding-right: 10px;
    }

    .subject-item {
        background: white;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .subject-item:hover {
        background: #e3f2fd;
        border-color: #2196F3 !important;
        transform: translateX(5px);
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
    }

    .subject-item.selected {
        background: #e8f5e9;
        border-color: #4CAF50 !important;
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
    }

    .subject-item.selected .fa-check-circle {
        display: inline-block !important;
    }

    .subject-item .form-check-input {
        width: 1.2rem;
        height: 1.2rem;
        margin-top: 0.3rem;
    }

    .subject-item label {
        padding-left: 10px;
        margin-bottom: 0;
    }

    .subject-list::-webkit-scrollbar {
        width: 8px;
    }

    .subject-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .subject-list::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    .subject-list::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    #subjectSearch {
        border: 2px solid #dee2e6;
        transition: border-color 0.3s ease;
    }

    #subjectSearch:focus {
        border-color: #2196F3;
        box-shadow: 0 0 0 0.2rem rgba(33, 150, 243, 0.25);
    }

    #enrollButton.disabled {
        opacity: 0.5;
        cursor: not-allowed !important;
        background-color: #6c757d !important;
        border-color: #6c757d !important;
    }

    #enrollButton.disabled:hover {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
    }
</style>
@endsection

@section('page_js')
@if(isset($row))
<script type="application/json" id="semester-data" data-selected="{{ $enroll->semester_id ?? '' }}" data-label="{{ __('select') }}">
    @json($semesterOptions ?? [])
</script>
<script type="text/javascript">
"use strict";
const dataNode = document.getElementById('semester-data');
const semesterSelect = document.getElementById('semester');
const semesterYearSelect = document.getElementById('semester_year');
let semesterLookup = {};
let currentlySelectedSemester = null;
let selectLabel = '';

if (dataNode) {
    try {
        semesterLookup = JSON.parse(dataNode.textContent || '{}');
    } catch (error) {
        semesterLookup = {};
    }
    currentlySelectedSemester = dataNode.dataset.selected || null;
    selectLabel = dataNode.dataset.label || '';
}

const buildSemesterOptions = (year, selectedId = null) => {
    if(!semesterSelect){
        return;
    }
    semesterSelect.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = selectLabel || 'Select';
        semesterSelect.appendChild(placeholderOption);

    // Check both static lookup and dynamic data
    let yearData = semesterLookup[year];
    if (!yearData && window.semestersByYear && window.semestersByYear[year]) {
        yearData = window.semestersByYear[year];
    }
    
    if(!year || !yearData){
        return;
    }

    yearData.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.title;
        if(selectedId && String(selectedId) === String(item.id)){
            option.selected = true;
        }
        semesterSelect.appendChild(option);
    });
};

if(semesterYearSelect){
    semesterYearSelect.addEventListener('change', function(){
        buildSemesterOptions(this.value);
        $('.next_semester').trigger('change');
    });

    const preselectedYear = semesterYearSelect.value;
    buildSemesterOptions(preselectedYear, currentlySelectedSemester);
}

// Next Section
$(".next_semester").on('change',function(e){
  e.preventDefault(e);
  var section=$(".next_section");
  var selectedProgram = $('#program').val() || '{{ $targetProgramId ?? $row->program_id }}';
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  $.ajax({
    type:'POST',
    url: "{{ route('filter-section') }}",
    data:{
      _token:$('input[name=_token]').val(),
      semester: $(this).val(),
      program: selectedProgram
    },
    success:function(response){
        // var jsonData=JSON.parse(response);
        $('option', section).remove();
        $('.next_section').append('<option value="">{{ __("select") }}</option>');
        $.each(response, function(){
          $('<option/>', {
            'value': this.id,
            'text': this.title
          }).appendTo('.next_section');
        });
      }

  });
});

// Next Subject
$(".next_section").on('change',function(e){
  e.preventDefault(e);
  var subject=$(".next_subject");
  var selectedProgram = $('#program').val() || '{{ $targetProgramId ?? $row->program_id }}';
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  $.ajax({
    type:'POST',
    url: "{{ route('filter-enroll-subject') }}",
    data:{
      _token:$('input[name=_token]').val(),
      section: $(this).val(),
      semester: $('.next_semester option:selected').val(),
      program: selectedProgram
    },
    success:function(response){
        // Clear all checkboxes first
        $('.subject-checkbox').prop('checked', false);
        $('.subject-item').removeClass('selected');
        
        // Check the subjects returned by the server
        $.each(response, function(){
          $('#subject_' + this.id).prop('checked', true);
          $('#subject_' + this.id).closest('.subject-item').addClass('selected');
        });
        
        // Update statistics
        updateSelectedCount();
      }

  });
});

// Enhanced Subject Selection Functionality
function updateSelectedCount() {
    const checkedBoxes = $('.subject-checkbox:checked');
    const count = checkedBoxes.length;
    let totalCredits = 0;
    
    checkedBoxes.each(function() {
        const creditHour = parseFloat($(this).closest('.subject-item').data('credit-hour')) || 0;
        totalCredits += creditHour;
    });
    
    $('#selectedCount').text(count);
    $('#totalCredits').text(totalCredits.toFixed(2));
    
    // Check max credit limit
    @if(isset($maxCreditLimit) && $maxCreditLimit !== null)
    const maxLimit = {{ $maxCreditLimit }};
    if(totalCredits > maxLimit) {
        $('#creditWarning').show();
        $('#totalCredits').addClass('text-danger fw-bold').removeClass('text-muted');
    } else {
        $('#creditWarning').hide();
        $('#totalCredits').removeClass('text-danger fw-bold').addClass('text-muted');
    }
    @endif
}

// Checkbox change handler
$(document).on('change', '.subject-checkbox', function() {
    const subjectItem = $(this).closest('.subject-item');
    if($(this).is(':checked')) {
        subjectItem.addClass('selected');
    } else {
        subjectItem.removeClass('selected');
    }
    updateSelectedCount();
});

// Click on subject item to toggle checkbox
$(document).on('click', '.subject-item', function(e) {
    if(!$(e.target).is('input[type="checkbox"]')) {
        const checkbox = $(this).find('.subject-checkbox');
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    }
});

// Search functionality
$('#subjectSearch').on('keyup', function() {
    const searchTerm = $(this).val().toLowerCase();
    
    $('.subject-item').each(function() {
        const code = $(this).data('subject-code');
        const title = $(this).data('subject-title');
        
        if(code.includes(searchTerm) || title.includes(searchTerm)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// Select All button
$('#selectAllSubjects').on('click', function() {
    $('.subject-item:visible .subject-checkbox').prop('checked', true).trigger('change');
});

// Clear All button
$('#clearAllSubjects').on('click', function() {
    $('.subject-checkbox').prop('checked', false).trigger('change');
});

// Initialize count on page load
updateSelectedCount();

// ===== Program Change Detection and Validation =====
const originalProgramId = {{ $targetProgramId ?? $row->program_id }};
const studentId = {{ $row->id }};
let currentValidation = null;
let isProgramChanging = false;

// Detect program change
$('#program').on('change', function() {
    const selectedProgramId = parseInt($(this).val());
    isProgramChanging = selectedProgramId !== originalProgramId && selectedProgramId > 0;
    
    $('#is_program_change').val(isProgramChanging ? '1' : '0');
    
    // Fetch related data for the selected program
    if (selectedProgramId > 0) {
        fetchSessionsForProgram(selectedProgramId);
        fetchSemestersForProgram(selectedProgramId);
        fetchSubjectsForProgram(selectedProgramId);
        
        // Clear dependent dropdowns
        $('#section').html('<option value="">{{ __("select") }}</option>');
    }
    
    if (isProgramChanging) {
        // Show warnings
        $('#programChangeAlert').slideDown();
        $('#programChangeReasonContainer').slideDown();
        $('#newMatriculePreview').slideDown(); // Show matricule preview
        $('#program_change_reason').prop('required', true);
        
        // Fetch validation data
        fetchProgramValidation(selectedProgramId);
    } else {
        // Hide warnings
        $('#programChangeAlert').slideUp();
        $('#programChangeReasonContainer').slideUp();
        $('#validationWarnings').slideUp();
        $('#newMatriculePreview').slideUp(); // Hide matricule preview
        $('#program_change_reason').prop('required', false);
        currentValidation = null;
    }
});

// Fetch sessions for selected program
function fetchSessionsForProgram(programId) {
    $.ajax({
        url: "{{ route('filter-session') }}",
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            program: programId
        },
        success: function(sessions) {
            $('#session').html('<option value="">{{ __("select") }}</option>');
            sessions.forEach(function(session) {
                $('#session').append('<option value="' + session.id + '">' + session.title + '</option>');
            });
        },
        error: function(xhr) {
            console.error('Session fetch error:', xhr);
        }
    });
}

// Fetch semesters for selected program
function fetchSemestersForProgram(programId) {
    $.ajax({
        url: "{{ route('filter-semester') }}",
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            program: programId
        },
        success: function(response) {
            // Update semester year dropdown
            $('#semester_year').html('<option value="">{{ __("select") }}</option>');
            
            // Group semesters by year
            const semestersByYear = {};
            response.forEach(function(semester) {
                if (semester.year) {
                    if (!semestersByYear[semester.year]) {
                        semestersByYear[semester.year] = [];
                    }
                    semestersByYear[semester.year].push(semester);
                }
            });
            
            // Populate year dropdown
            Object.keys(semestersByYear).sort().forEach(function(year) {
                $('#semester_year').append('<option value="' + year + '">{{ __("field_year") }} ' + year + '</option>');
            });
            
            // Store semester data for later use
            window.semestersByYear = semestersByYear;
            
            // Clear semester dropdown
            $('#semester').html('<option value="">{{ __("select") }}</option>');
        },
        error: function(xhr) {
            console.error('Semester fetch error:', xhr);
        }
    });
}

// Fetch subjects for selected program
function fetchSubjectsForProgram(programId) {
    // Show loading state
    $('#subjectList').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Loading subjects for selected program...</div>');
    
    $.ajax({
        url: "{{ route('filter-subject') }}",
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            program: programId
        },
        success: function(subjects) {
            // Rebuild subject list
            let html = '';
            
            if (subjects.length === 0) {
                html = '<div class="alert alert-warning">No subjects found for the selected program.</div>';
            } else {
                subjects.forEach(function(subject) {
                    html += '<div class="subject-item form-check border rounded p-3 mb-2" ' +
                            'data-subject-code="' + (subject.code || '').toLowerCase() + '" ' +
                            'data-subject-title="' + (subject.title || '').toLowerCase() + '" ' +
                            'data-credit-hour="' + (subject.credit_hour || 0) + '">';
                    html += '<input class="form-check-input subject-checkbox" type="checkbox" ' +
                            'name="subjects[]" value="' + subject.id + '" id="subject_' + subject.id + '">';
                    html += '<label class="form-check-label w-100" for="subject_' + subject.id + '" style="cursor: pointer;">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div>';
                    html += '<strong>' + (subject.code || '') + '</strong> - ' + (subject.title || '');
                    html += '<div class="mt-1">';
                    html += '<span class="badge bg-info text-white">' + (subject.credit_hour || 0) + ' Credits</span>';
                    
                    if (subject.subject_type == 1) {
                        html += '<span class="badge bg-danger text-white">Compulsory</span>';
                    } else {
                        html += '<span class="badge bg-success text-white">Optional</span>';
                    }
                    
                    html += '</div></div>';
                    html += '<i class="fas fa-check-circle text-success" style="font-size: 1.5rem; display: none;"></i>';
                    html += '</div></label></div>';
                });
            }
            
            $('#subjectList').html(html);
            
            // Reset selection count
            updateSelectedCount();
        },
        error: function(xhr) {
            console.error('Subject fetch error:', xhr);
            $('#subjectList').html(
                '<div class="alert alert-danger">Failed to load subjects. Please refresh and try again.</div>'
            );
        }
    });
}

// Fetch program validation from server
function fetchProgramValidation(programId) {
    $.ajax({
        url: "{{ route('admin.single-enroll.validate-swap') }}",
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            student_id: studentId,
            program_id: programId
        },
        success: function(response) {
            if (response.success) {
                currentValidation = response.validation;
                displayValidationWarnings(currentValidation);
                updateMatriculePreview(currentValidation); // NEW: Update matricule preview
            }
        },
        error: function(xhr) {
            console.error('Validation fetch error:', xhr);
            $('#validationWarnings').html(
                '<div class="alert alert-danger">Failed to load validation data. Please refresh and try again.</div>'
            ).slideDown();
        }
    });
}

// NEW: Update matricule preview display
function updateMatriculePreview(validation) {
    if (!validation) return;
    
    // Get level badge colors
    const levelColors = {
        'A': '#38f9d7', // Undergraduate - Green gradient
        'M': '#f5576c', // Masters - Orange/Red gradient
        'D': '#00f2fe'  // Doctoral - Blue gradient
    };
    
    const levelNames = {
        'A': 'Undergraduate',
        'M': 'Masters',
        'D': 'Doctoral'
    };
    
    if (validation.new_matricule) {
        // Check if current matricule is just a number (old format) or proper format (PAX...)
        const currentMatricule = '{{ $displayEnroll->matricule ?? $latestStudentEnroll->matricule ?? $row->student_id }}';
        const isProperFormat = validation.new_matricule.startsWith('PAX');
        const needsGeneration = !currentMatricule.startsWith('PAX');
        
        if (validation.generates_new_matricule || needsGeneration) {
            // Show NEW matricule section
            $('#newMatriculeDisplay').slideDown();
            $('#sameMatriculeDisplay').slideUp();
            
            // Update the badge with new matricule
            $('#newMatriculeBadge').text(validation.new_matricule);
            
            // Determine new level from matricule
            let newLevel = 'A'; // Default undergraduate
            if (validation.new_matricule.includes('DF')) newLevel = 'D';
            else if (validation.new_matricule.includes('MF')) newLevel = 'M';
            
            // Update level badge
            const levelColor = levelColors[newLevel] || '#38f9d7';
            const levelName = levelNames[newLevel] || 'Undergraduate';
            $('#newLevelBadge').text(levelName).css('background', levelColor);
            
        } else {
            // Proper matricule exists - will be maintained
            $('#newMatriculeDisplay').slideUp();
            $('#sameMatriculeDisplay').slideDown();
            $('#sameMatriculeDisplay').find('.badge').text('Matricule will be maintained: ' + validation.new_matricule);
        }
    } else {
        // No matricule info
        $('#newMatriculeDisplay').slideUp();
        $('#sameMatriculeDisplay').slideUp();
    }
}

// Display validation warnings
function displayValidationWarnings(validation) {
    if (!validation || !validation.is_program_change) {
        $('#validationWarnings').slideUp();
        return;
    }
    
    let html = '<div class="alert-container mt-3 mb-3">';
    
    // NO BLOCKERS - Program change always allowed (creates NEW enrollment)
    // Just show warnings and info for administrator awareness
    
    // Warnings (should review)
    if (validation.warnings && validation.warnings.length > 0) {
        html += '<div class="alert alert-warning"><h6><i class="fas fa-exclamation-triangle"></i> <strong>Important Warnings:</strong></h6><ul class="mb-0">';
        validation.warnings.forEach(function(warning) {
            html += '<li><strong>' + warning.message + '</strong>';
            
            // Show details if available
            if (warning.details && warning.details.length > 0) {
                html += '<ul class="mt-2">';
                warning.details.forEach(function(detail) {
                    if (typeof detail === 'object') {
                        html += '<li>';
                        Object.keys(detail).forEach(function(key) {
                            html += '<strong>' + key.replace(/_/g, ' ') + ':</strong> ' + detail[key] + ' ';
                        });
                        html += '</li>';
                    }
                });
                html += '</ul>';
            }
            html += '</li>';
        });
        html += '</ul></div>';
    }
    
    // Info (general information)
    if (validation.info && validation.info.length > 0) {
        html += '<div class="alert alert-info"><h6><i class="fas fa-info-circle"></i> <strong>Program Change Information:</strong></h6><ul class="mb-0">';
        validation.info.forEach(function(info) {
            const message = typeof info === 'object' ? info.message : info;
            html += '<li>' + message + '</li>';
        });
        html += '</ul></div>';
    }
    
    html += '</div>';
    
    $('#validationWarnings').html(html).slideDown();
}

// Handle enroll button click
$('#enrollButton').on('click', function(e) {
    e.preventDefault();
    
    // Validate form
    const form = $('#enrollmentForm')[0];
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // If program is changing, check reason is provided
    if (isProgramChanging) {
        const reason = $('#program_change_reason').val().trim();
        if (!reason) {
            $('#program_change_reason').addClass('is-invalid');
            alert('Please provide a reason for the program change.');
            return;
        }
    }
    
    // Build and show confirmation modal
    buildConfirmationModal();
    $('#confirmModal').modal('show');
});

// Build dynamic confirmation modal
function buildConfirmationModal() {
    let modalTitle = '{{ __("Enrollment Readiness Check") }}';
    let headerClass = 'bg-warning text-dark';
    
    if (isProgramChanging) {
        modalTitle = '<i class="fas fa-exchange-alt"></i> Program Change Confirmation';
        headerClass = 'bg-danger text-white';
    }
    
    $('#confirmModalTitle').html(modalTitle);
    $('#confirmModalHeader').removeClass().addClass('modal-header ' + headerClass);
    
    // Build modal body
    let bodyHtml = '<div class="confirmation-content">';
    
    if (isProgramChanging && currentValidation) {
        bodyHtml += '<div class="alert alert-danger mb-3">';
        bodyHtml += '<h6><i class="fas fa-exclamation-triangle"></i> <strong>You are about to change this student\'s program!</strong></h6>';
        bodyHtml += '<p class="mb-2">This will create a NEW enrollment record with important implications.</p>';
        
        // NEW: Show matricule change in modal
        if (currentValidation.generates_new_matricule && currentValidation.new_matricule) {
            bodyHtml += '<div class="p-3 bg-success text-white rounded mt-2">';
            bodyHtml += '<h6 class="mb-2"><i class="fas fa-id-card"></i> New Matricule Will Be Generated:</h6>';
            bodyHtml += '<h5 class="mb-0 font-weight-bold">' + currentValidation.new_matricule + '</h5>';
            bodyHtml += '</div>';
        } else if (currentValidation.new_matricule) {
            bodyHtml += '<div class="p-3 bg-info text-white rounded mt-2">';
            bodyHtml += '<p class="mb-0"><i class="fas fa-info-circle"></i> Current matricule will be retained: <strong>' + currentValidation.new_matricule + '</strong></p>';
            bodyHtml += '</div>';
        }
        
        bodyHtml += '</div>';
        
        // Add validation summary to modal
        bodyHtml += '<div class="validation-summary">';
        bodyHtml += displayValidationWarnings(currentValidation);
        bodyHtml += '</div>';
        
        // Show reason
        const reason = $('#program_change_reason').val();
        if (reason) {
            bodyHtml += '<div class="alert alert-info">';
            bodyHtml += '<strong>Program Change Reason:</strong><br>' + reason;
            bodyHtml += '</div>';
        }
    }
    
    // Add original enrollment check content
    bodyHtml += `
        @if(isset($row) && isset($enroll))
        <h6 class="text-primary mb-3"><i class="fas fa-graduation-cap"></i> {{ __('Current Enrollment Summary') }}</h6>
        <div class="row mb-3">
            <div class="col-md-6">
                <p class="mb-1"><strong>{{ __('field_session') }}:</strong> {{ $enroll->session->title ?? 'N/A' }}</p>
                <p class="mb-1"><strong>{{ __('field_semester') }}:</strong> {{ $enroll->semester->title ?? 'N/A' }}</p>
                <p class="mb-1"><strong>{{ __('field_section') }}:</strong> {{ $enroll->section->title ?? 'N/A' }}</p>
            </div>
        </div>
        @endif
    `;
    
    bodyHtml += '<div class="alert alert-warning mt-3 mb-0">';
    bodyHtml += '<strong><i class="fas fa-exclamation-triangle"></i> {{ __("Warning:") }}</strong> ';
    bodyHtml += '{{ __("Proceeding will enroll the student. This action should only be taken after verifying all requirements.") }}';
    bodyHtml += '</div>';
    
    bodyHtml += '</div>';
    
    $('#confirmModalBody').html(bodyHtml);
}
</script>
@endif
@endsection