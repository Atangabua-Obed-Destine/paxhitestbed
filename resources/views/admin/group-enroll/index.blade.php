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
                        @php
                            $filterPreselectedYear = '';
                            if(!empty($selected_semester_year) && $selected_semester_year !== '0'){
                                $filterPreselectedYear = $selected_semester_year;
                            }
                            elseif(!empty($selected_semester) && ($semesters ?? collect())->isNotEmpty()){
                                $matchedSemester = ($semesters ?? collect())->firstWhere('id', $selected_semester);
                                $filterPreselectedYear = optional($matchedSemester)->year;
                            }
                        @endphp
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6">
                                  <label for="faculty">{{ __('field_faculty') }} <span>*</span></label>
                                  <select class="form-control filter-faculty" name="faculty" id="faculty" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($faculties ?? collect())->sortBy('title') as $facultyItem)
                                        <option value="{{ $facultyItem->id }}" @if($selected_faculty == $facultyItem->id) selected @endif>{{ $facultyItem->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_faculty') }}
                                    </div>
                                </div>
                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6">
                                  <label for="program">{{ __('field_program') }} <span>*</span></label>
                                  <select class="form-control filter-program" name="program" id="program" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($programs ?? collect())->sortBy('title') as $programItem)
                                        <option value="{{ $programItem->id }}" @if($selected_program == $programItem->id) selected @endif>{{ $programItem->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_program') }}
                                    </div>
                                </div>
                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6">
                                  <label for="session">{{ __('field_session') }} <span>*</span></label>
                                  <select class="form-control filter-session" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($sessions ?? collect())->sortByDesc('id') as $sessionItem)
                                        <option value="{{ $sessionItem->id }}" @if($selected_session == $sessionItem->id) selected @endif>{{ $sessionItem->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_session') }}
                                    </div>
                                </div>
                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6">
                                  <label for="semester_year">{{ __('field_year') }} <span>*</span></label>
                                  <select class="form-control filter-semester-year" name="semester_year" id="semester_year" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($semesterOptions ?? []) as $year => $items)
                                        <option value="{{ $year }}" @if(!empty($filterPreselectedYear) && (string)$filterPreselectedYear === (string)$year) selected @endif>{{ __('field_year') }} {{ $year }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_year') }}
                                    </div>
                                </div>
                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6">
                                  <label for="semester">{{ __('field_semester') }} <span>*</span></label>
                                  <select class="form-control filter-semester" name="semester" id="semester" required>
                                        <option value="">{{ __('select') }}</option>
                                  </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_semester') }}
                                    </div>
                                </div>
                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6">
                                  <label for="section">{{ __('field_section') }} <span>*</span></label>
                                    <select class="form-control filter-section" name="section" id="section" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($sections ?? collect())->sortBy('title') as $sectionItem)
                                        <option value="{{ $sectionItem->id }}" @if($selected_section == $sectionItem->id) selected @endif>{{ $sectionItem->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_section') }}
                                    </div>
                                </div>

                                <div class="form-group col-xl-2 col-lg-3 col-md-3 col-sm-6 align-self-end">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                @isset($rows)
                <form action="{{ route($route.'.store') }}" method="post">
                @csrf
                @if(count($rows) > 0)
                @php
                    $preselectedYear = $selected_semester_year ?? '';
                    if(empty($preselectedYear) || $preselectedYear === '0'){
                        $preselectedYear = optional($semesters->firstWhere('id', $selected_semester))->year;
                    }
                @endphp
                <div class="card">
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                        <div class="checkbox checkbox-success d-inline">
                                            <input type="checkbox" id="checkbox" class="all_select" checked>
                                            <label for="checkbox" class="cr" style="margin-bottom: 0px;"></label>
                                        </div>
                                        </th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_gender') }}</th>
                                        <th>{{ __('field_total_credit_hour') }}</th>
                                        <th>{{ __('field_cumulative_gpa') }}</th>
                                        <th>{{ __('field_batch') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )

                                    @php
                                        $total_credits = 0;
                                        $total_cgpa = 0;
                                    @endphp
                                    @foreach( $row->studentEnrolls as $key => $item )
                                        {{-- Only count enrollments for the selected program --}}
                                        @if($item->program_id != $selected_program)
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

                                    <input type="text" name="program" value="{{ $selected_program }}" hidden>
                                    <tr>
                                        <td>
                                        <div class="checkbox checkbox-primary d-inline">
                                            <input type="checkbox" name="students[]" id="checkbox-{{ $row->id }}" value="{{ $row->id }}" checked>
                                            <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                        </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.student.show', $row->id) }}" target="_blank">
                                            #{{ $row->filteredEnroll->matricule ?? $row->currentEnroll->matricule ?? $row->student_id }}
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
                                        <td>{{ round($total_credits, 2) }}</td>
                                        <td>
                                            @php
                                            if($total_credits <= 0){
                                                $total_credits = 1;
                                            }
                                            $com_gpa = $total_cgpa / $total_credits;
                                            echo number_format((float)$com_gpa, 2, '.', '');
                                            @endphp
                                        </td>
                                        <td>{{ $row->batch->title ?? '' }}</td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
                

                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('field_next_enrollment') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="row gx-2">
                            <div class="form-group col-md-3">
                                <label for="session">{{ __('field_session') }} <span>*</span></label>
                                <select class="form-control" name="session" id="session" required>
                                  <option value="">{{ __('select') }}</option>
                                  @foreach( $sessions as $session )
                                  <option value="{{ $session->id }}" @if( $selected_session == $session->id) selected @endif>{{ $session->title }}</option>
                                  @endforeach
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_session') }}
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="next_semester_year">{{ __('field_year') }} <span>*</span></label>
                                <select class="form-control" name="semester_year" id="next_semester_year" required>
                                                                    <option value="">{{ __('select') }}</option>
                                                                    @foreach($semesterOptions ?? [] as $year => $items)
                                                                    <option value="{{ $year }}" @if(!empty($preselectedYear) && (string)$preselectedYear === (string)$year) selected @endif>{{ __('field_year') }} {{ $year }}</option>
                                                                    @endforeach
                                                                </select>

                                                                <div class="invalid-feedback">
                                                                    {{ __('required_field') }} {{ __('field_year') }}
                                                                </div>
                                                        </div>
                            <div class="form-group col-md-3">
                                                                <label for="next_semester">{{ __('field_semester') }} <span>*</span></label>
                                                                <select class="form-control next_semester" name="semester" id="next_semester" required>
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
                                  <option value="{{ $section->id }}" @if( $selected_section == $section->id) selected @endif>{{ $section->title }}</option>
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
                                            <button type="button" class="btn btn-sm btn-secondary" id="deselectAllSubjects">
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
                                                            @elseif($subject->subject_type == 2)
                                                                <span class="badge bg-warning text-white">University Requirement</span>
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
                            <div class="form-group col-md-3">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal">
                                    <i class="fas fa-exchange"></i> {{ __('btn_enroll') }}
                                </button>
                                <!-- Include Confirm modal -->
                                @include($view.'.confirm')
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                </form>

                @if(count($rows) < 1)
                <div class="card">
                    <div class="card-block">
                        <h5>{{ __('no_result_found') }}</h5>
                    </div>
                </div>
                @endif
                @endisset

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
</style>
@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
<script type="application/json" id="filter-semester-data" data-selected="{{ $selected_semester !== '0' ? $selected_semester : '' }}" data-year="{{ !empty($filterPreselectedYear) ? $filterPreselectedYear : '' }}">
    @json($semesterOptions ?? [])
</script>
@if(isset($rows) && count($rows) > 0)
@php
    $preselectedYear = $preselectedYear ?? $filterPreselectedYear;
@endphp
<script type="application/json" id="group-semester-data" data-selected="{{ $selected_semester !== '0' ? $selected_semester : '' }}" data-year="{{ !empty($preselectedYear) ? $preselectedYear : '' }}">
    @json($semesterOptions ?? [])
</script>
@endif
<script type="text/javascript">
"use strict";
const translationSelect = <?php echo json_encode(__('select')); ?>;
const translationYear = <?php echo json_encode(__('field_year')); ?>;
const selectedProgramId = <?php echo json_encode($selected_program); ?>;
const select2Available = typeof $.fn.select2 !== "undefined";
const csrfTokenElement = document.querySelector('meta[name="csrf-token"]');
const csrfToken = csrfTokenElement ? csrfTokenElement.getAttribute('content') : '';

if (csrfToken) {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    });
}

const resetDropdown = ($element, placeholder = translationSelect) => {
    if(!$element || !$element.length){
        return;
    }
    $element.empty();
    $element.append($('<option/>', {
        value: '',
        text: placeholder
    }));
};

const parseSemesterLookup = (node) => {
    if(!node){
        return {};
    }
    try {
        return JSON.parse(node.textContent || '{}');
    } catch (error) {
        return {};
    }
};

const buildYearOptions = ($select, lookup, selectedYear = '') => {
    if(!$select || !$select.length){
        return;
    }
    resetDropdown($select);
    const years = Object.keys(lookup).sort((a, b) => Number(a) - Number(b));
    years.forEach((year) => {
        const option = $('<option/>', {
            value: year,
            text: `${translationYear} ${year}`
        });
        if(selectedYear && String(selectedYear) === String(year)){
            option.attr('selected', 'selected');
        }
        $select.append(option);
    });
};

const buildSemesterOptions = ($select, lookup, year, selectedSemester = '') => {
    if(!$select || !$select.length){
        return;
    }
    resetDropdown($select);
    if(!year || !lookup[year]){
        return;
    }
    lookup[year].forEach((item) => {
        const option = $('<option/>', {
            value: item.id,
            text: item.title
        });
        if(selectedSemester && String(selectedSemester) === String(item.id)){
            option.attr('selected', 'selected');
        }
        $select.append(option);
    });
};

const deriveYearForSemester = (lookup, semesterId) => {
    if(!semesterId){
        return '';
    }
    let derived = '';
    Object.keys(lookup).some((year) => {
        const match = lookup[year].some((item) => String(item.id) === String(semesterId));
        if(match){
            derived = year;
            return true;
        }
        return false;
    });
    return derived;
};

const updateLookupWithRows = (rows) => {
    const updated = {};
    if(Array.isArray(rows)){
        rows.forEach((item) => {
            if(item && item.year !== null && item.year !== undefined){
                const yearKey = item.year;
                if(!updated[yearKey]){
                    updated[yearKey] = [];
                }
                updated[yearKey].push({
                    id: item.id,
                    title: item.title
                });
            }
        });
        Object.keys(updated).forEach((key) => {
            updated[key].sort((a, b) => Number(a.id) - Number(b.id));
        });
    }
    return updated;
};

const filterNode = document.getElementById('filter-semester-data');
let filterLookup = parseSemesterLookup(filterNode);
let filterSelectedSemester = filterNode ? (filterNode.dataset.selected || '') : '';
let filterSelectedYear = filterNode ? (filterNode.dataset.year || '') : '';

if(!filterSelectedYear && filterSelectedSemester){
    filterSelectedYear = deriveYearForSemester(filterLookup, filterSelectedSemester);
}

const $filterFaculty = $('.filter-faculty');
const $filterProgram = $('.filter-program');
const $filterSession = $('.filter-session');
const $filterYear = $('#semester_year');
const $filterSemester = $('.filter-semester');
const $filterSection = $('.filter-section');

const updateProgramDisabledState = () => {
    const hasFaculty = Boolean($filterFaculty.val());
    const hasPrograms = $filterProgram.children('option').length > 1;
    const shouldDisable = !hasFaculty || !hasPrograms;
    $filterProgram.prop('disabled', shouldDisable);
    if(shouldDisable){
        $filterProgram.val('');
    }
    if(select2Available){
        $filterProgram.trigger('change.select2');
    }
};

buildYearOptions($filterYear, filterLookup, filterSelectedYear);
const initialFilterYear = $filterYear.val() || filterSelectedYear;
buildSemesterOptions($filterSemester, filterLookup, initialFilterYear, filterSelectedSemester);
updateProgramDisabledState();

const resetFilterChain = () => {
    resetDropdown($filterProgram);
    resetDropdown($filterSession);
    resetDropdown($filterYear);
    resetDropdown($filterSemester);
    resetDropdown($filterSection);
    filterLookup = {};
    updateProgramDisabledState();
};

$filterFaculty.on('change', function(e){
    e.preventDefault();
    const facultyId = $(this).val();
    resetFilterChain();

    if(!facultyId){
        return;
    }

    $.post("{{ route('filter-program') }}", { faculty: facultyId }, function(response){
        if(Array.isArray(response)){
            response.forEach(function(item){
                $filterProgram.append($('<option/>', {
                    value: item.id,
                    text: item.title
                }));
            });
        }
        updateProgramDisabledState();
    });
});

$filterProgram.on('change', function(e){
    e.preventDefault();
    const programId = $(this).val();
    resetDropdown($filterSession);
    resetDropdown($filterYear);
    resetDropdown($filterSemester);
    resetDropdown($filterSection);
    filterLookup = {};

    if(!programId){
        return;
    }

    $.post("{{ route('filter-session') }}", { program: programId }, function(response){
        if(Array.isArray(response)){
            response.forEach(function(item){
                $filterSession.append($('<option/>', {
                    value: item.id,
                    text: item.title
                }));
            });
        }
    });

    $.post("{{ route('filter-semester') }}", { program: programId }, function(response){
        filterLookup = updateLookupWithRows(response);
        buildYearOptions($filterYear, filterLookup, '');
        buildSemesterOptions($filterSemester, filterLookup, '', '');
    });
    updateProgramDisabledState();
});

$filterYear.on('change', function(e){
    e.preventDefault();
    const selectedYear = $(this).val();
    buildSemesterOptions($filterSemester, filterLookup, selectedYear, '');
    resetDropdown($filterSection);
});

$filterSemester.on('change', function(e){
    e.preventDefault();
    const semesterId = $(this).val();
    const programId = $filterProgram.val();
    resetDropdown($filterSection);

    if(!semesterId || !programId){
        return;
    }

    $.post("{{ route('filter-section') }}", { semester: semesterId, program: programId }, function(response){
        if(Array.isArray(response)){
            response.forEach(function(item){
                $filterSection.append($('<option/>', {
                    value: item.id,
                    text: item.title
                }));
            });
        }
    });
});

const getActiveProgramId = () => {
    const programVal = $filterProgram.val();
    if(programVal && programVal !== '0'){
        return programVal;
    }
    if(selectedProgramId && selectedProgramId !== '0'){
        return selectedProgramId;
    }
    return '';
};

const groupNode = document.getElementById('group-semester-data');
let groupLookup = parseSemesterLookup(groupNode);
let groupSelectedSemester = groupNode ? (groupNode.dataset.selected || '') : '';
let groupSelectedYear = groupNode ? (groupNode.dataset.year || '') : '';
const rawInitialGroupSection = <?php echo json_encode($selected_section); ?>;
const initialGroupSectionId = rawInitialGroupSection && rawInitialGroupSection !== '0' ? rawInitialGroupSection : '';
let groupInitialLoad = true;

if(!groupSelectedYear && groupSelectedSemester){
    groupSelectedYear = deriveYearForSemester(groupLookup, groupSelectedSemester);
}

const $groupYear = $('#next_semester_year');
const $groupSemester = $('.next_semester');
const $groupSection = $('.next_section');
const $groupSubject = $('.next_subject');

if($groupYear.length){
    buildYearOptions($groupYear, groupLookup, groupSelectedYear);
    const initialGroupYear = $groupYear.val() || groupSelectedYear;
    buildSemesterOptions($groupSemester, groupLookup, initialGroupYear, groupSelectedSemester);
}

const resetGroupSection = () => {
    resetDropdown($groupSection);
};

const resetGroupSubjects = () => {
    // Clear all checkboxes
    $('.subject-checkbox').prop('checked', false);
    $('.subject-item').removeClass('selected');
    updateSelectedCount();
};

const resetGroupSectionAndSubjects = () => {
    resetGroupSection();
    resetGroupSubjects();
};

$(".all_select").on('click', function(){
    const checked = $(this).is(":checked");
    $("input:checkbox").prop('checked', checked);
});

$groupYear.on('change', function(){
    const year = $(this).val();
    buildSemesterOptions($groupSemester, groupLookup, year, '');
    resetGroupSectionAndSubjects();
});

$groupSemester.on('change', function(e){
    e.preventDefault();
    const semesterId = $(this).val();
    const programId = getActiveProgramId();
    resetGroupSectionAndSubjects();

    if(!semesterId || !programId){
        groupInitialLoad = false;
        return;
    }

    $.post("{{ route('filter-section') }}", { semester: semesterId, program: programId }, function(response){
        if(Array.isArray(response)){
            response.forEach(function(item){
                const option = $('<option/>', {
                    value: item.id,
                    text: item.title
                });
                if(groupInitialLoad && initialGroupSectionId && String(initialGroupSectionId) === String(item.id)){
                    option.attr('selected', 'selected');
                }
                $groupSection.append(option);
            });
            if(groupInitialLoad && initialGroupSectionId){
                $groupSection.trigger('change');
            }
        }
        groupInitialLoad = false;
    });
});

$groupSection.on('change', function(e){
    e.preventDefault();
    const sectionId = $(this).val();
    const semesterId = $groupSemester.val();
    const programId = getActiveProgramId();

    if(!sectionId || !semesterId || !programId){
        return;
    }

    $.post("{{ route('filter-enroll-subject') }}", {
        section: sectionId,
        semester: semesterId,
        program: programId
    }, function(response){
        // Clear all checkboxes first
        $('.subject-checkbox').prop('checked', false);
        $('.subject-item').removeClass('selected');
        
        // Check the subjects returned by the server
        if(Array.isArray(response)){
            response.forEach(function(item){
                $('#subject_' + item.id).prop('checked', true);
                $('#subject_' + item.id).closest('.subject-item').addClass('selected');
            });
        }
        
        // Update statistics
        updateSelectedCount();
    });
});

if(groupNode && $groupSemester.val()){
    $groupSemester.trigger('change');
}

// Enhanced Subject Selection Functionality - Define updateSelectedCount globally
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
    
    // Update visual state
    $('.subject-item').each(function() {
        if ($(this).find('.subject-checkbox').is(':checked')) {
            $(this).addClass('selected');
        } else {
            $(this).removeClass('selected');
        }
    });
}

$(document).ready(function() {
    // Checkbox change handler
    $(document).on('change', '.subject-checkbox', function() {
        updateSelectedCount();
    });
    
    // Click on subject item to toggle checkbox
    $(document).on('click', '.subject-item', function(e) {
        if(!$(e.target).is('input[type="checkbox"]')) {
            const checkbox = $(this).find('.subject-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });
    
    // Subject checkbox change
    $('.subject-checkbox').on('change', function() {
        updateSelectedCount();
    });
    
    // Click on subject item label to toggle checkbox
    $('.subject-item label').on('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            e.preventDefault();
            const checkbox = $(this).closest('.subject-item').find('.subject-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });
    
    // Select all subjects
    $('#selectAllSubjects').on('click', function() {
        $('.subject-item:visible .subject-checkbox').prop('checked', true).trigger('change');
    });
    
    // Deselect all subjects
    $('#deselectAllSubjects').on('click', function() {
        $('.subject-checkbox').prop('checked', false).trigger('change');
    });
    
    // Search functionality
    $('#subjectSearch').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        
        $('.subject-item').each(function() {
            const code = $(this).data('subject-code');
            const title = $(this).data('subject-title');
            
            if (code.includes(searchText) || title.includes(searchText)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Clear search on input clear
    $('#subjectSearch').on('search', function() {
        if ($(this).val() === '') {
            $('.subject-item').show();
            updateSelectedCount();
        }
    });
    
    // Initial count update
    updateSelectedCount();
    
    // Update when section changes and subjects are pre-selected
    $groupSection.on('change', function() {
        setTimeout(function() {
            updateSelectedCount();
        }, 500);
    });
});
</script>
@endsection