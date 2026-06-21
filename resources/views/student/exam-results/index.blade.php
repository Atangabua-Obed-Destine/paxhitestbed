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
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route) }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="session">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $session)
                                            <option value="{{ $session->id }}" @selected((string) $session->id === (string) $selected_session)>{{ $session->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_session') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="semester_year">{{ __('field_year') }}</label>
                                    <select class="form-control" name="semester_year" id="semester_year">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(collect($semesterOptions ?? [])->keys()->sort() as $yearKey)
                                            <option value="{{ $yearKey }}" @selected((string) $selected_semester_year === (string) $yearKey)>{{ __('field_year') }} {{ $yearKey }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_year') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="semester">{{ __('field_semester') }}</label>
                                    <select class="form-control" name="semester" id="semester" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($semesters as $semester)
                                            <option value="{{ $semester->id }}" @selected((string) $semester->id === (string) $selected_semester)>{{ $semester->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_semester') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="exam_type">{{ __('Exam Type') }}</label>
                                    <select class="form-control" name="exam_type" id="exam_type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($examTypes as $examType)
                                            <option value="{{ $examType->id }}" @selected((string) $examType->id === (string) $selected_exam_type)>{{ $examType->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('Exam Type') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-12 mt-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                    <a href="{{ route($route) }}" class="btn btn-light"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($result_blocked))
            <div class="row">
                <div class="col-md-12">
                    <div class="card border-danger" style="border-width:2px;">
                        <div class="card-header" style="background: linear-gradient(135deg, #c62828 0%, #6a1b1b 100%); color: white;">
                            <h5 class="mb-0 text-white">
                                <i class="fas fa-lock mr-1"></i>
                                {{ __('Results Withheld') }}
                            </h5>
                        </div>
                        <div class="card-block text-center" style="padding: 40px 20px;">
                            <div style="font-size: 64px; color: #c62828; margin-bottom: 16px;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h4 class="mb-3">{{ __('Your results for this semester have been withheld.') }}</h4>
                            <p class="text-muted mb-3" style="font-size: 16px;">
                                {{ __('Access to your exam results, downloads and grade summary for this semester has been temporarily suspended by the administration.') }}
                            </p>
                            <p class="mb-2"><strong>{{ __('Please contact the administration office for assistance.') }}</strong></p>
                            @if(!empty($result_block) && $result_block->blocked_at)
                                <small class="text-muted d-block mt-3">
                                    {{ __('Withheld since') }}: {{ \Carbon\Carbon::parse($result_block->blocked_at)->format('d M Y') }}
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @elseif($filtersApplied)
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px 4px 0 0;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                <div>
                                    <h5 class="mb-1 text-white">
                                        <i class="fas fa-chart-bar mr-1"></i>
                                        {{ $selected_exam_type_model->title ?? __('Exam Results') }}
                                    </h5>
                                    <small style="opacity: 0.85;">
                                        @php
                                            $sessionTitle = $sessions->firstWhere('id', (int)$selected_session)->title ?? '';
                                            $semesterTitle = $semesters->firstWhere('id', (int)$selected_semester)->title ?? '';
                                        @endphp
                                        @if($sessionTitle)
                                            <i class="fas fa-calendar-alt mr-1"></i> {{ $sessionTitle }}
                                        @endif
                                        @if($semesterTitle)
                                            &nbsp;&bull;&nbsp; <i class="fas fa-layer-group mr-1"></i> {{ $semesterTitle }}
                                        @endif
                                    </small>
                                </div>
                                @if(!$rows->isEmpty())
                                <div class="text-right mt-2 mt-md-0">
                                    <a href="{{ route('student.exam-results.download-pdf', ['session' => $selected_session, 'semester' => $selected_semester, 'exam_type' => $selected_exam_type]) }}"
                                       class="btn btn-sm btn-success mr-1" title="{{ __('Download PDF') }}">
                                        <i class="fas fa-file-pdf"></i> {{ __('Download PDF') }}
                                    </a>
                                    <button type="button" class="btn btn-sm btn-light" onclick="window.print()">
                                        <i class="fas fa-print"></i> {{ __('btn_print') }}
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="card-block">
                            @if($rows->isEmpty())
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">{{ __('no_result_found') }}</h6>
                                    <p class="text-muted small">{{ __('Results for this exam type have not been published yet.') }}</p>
                                </div>
                            @else
                                {{-- Summary Cards Row --}}
                                <div class="row mb-4">
                                    <div class="col-md-4 col-6 mb-2">
                                        <div class="border rounded p-3 text-center h-100" style="background: #f0f4ff;">
                                            <div class="text-muted small text-uppercase mb-1">{{ __('Courses') }}</div>
                                            <div class="h4 mb-0 font-weight-bold" style="color: #667eea;">{{ count($coursesData) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6 mb-2">
                                        <div class="border rounded p-3 text-center h-100" style="background: #f0f8ff;">
                                            <div class="text-muted small text-uppercase mb-1">{{ __('Credits Registered') }}</div>
                                            <div class="h4 mb-0 font-weight-bold" style="color: #138496;">{{ number_format($totalCreditsRegistered, 0) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6 mb-2">
                                        <div class="border rounded p-3 text-center h-100" style="background: #f0fff4;">
                                            <div class="text-muted small text-uppercase mb-1">{{ __('Credits Earned') }}</div>
                                            <div class="h4 mb-0 font-weight-bold" style="color: #27ae60;">{{ number_format($totalCreditsEarned, 0) }}</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Results Table --}}
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover mb-0" id="results-table" style="font-size: 0.9rem;">
                                        <thead>
                                            <tr style="background: #f8f9fa; color: #333 !important;">
                                                <th class="text-center" style="width: 4%; vertical-align: middle; color: #333 !important;">#</th>
                                                <th style="width: 10%; vertical-align: middle; color: #333 !important;">{{ __('field_code') }}</th>
                                                <th style="width: 28%; vertical-align: middle; color: #333 !important;">{{ __('field_subject') }}</th>
                                                <th class="text-center" style="width: 5%; vertical-align: middle; color: #333 !important;" title="{{ __('Credit Value') }}">CV</th>
                                                <th class="text-center" style="width: 7%; vertical-align: middle; background: #eef6ff; color: #333 !important;" title="{{ __('Attendance Mark') }}">ATT</th>
                                                <th class="text-center" style="width: 8%; vertical-align: middle; background: #fff8ee; color: #333 !important;" title="{{ __('Continuous Assessment') }}">CA</th>
                                                <th class="text-center" style="width: 8%; vertical-align: middle; background: #eef0ff; color: #333 !important;" title="{{ __('Exam Mark') }}">EX</th>
                                                <th class="text-center" style="width: 8%; vertical-align: middle; background: #f5f5f5; font-weight: bold; color: #333 !important;" title="{{ __('Total Marks') }}">TOT</th>
                                                <th class="text-center" style="width: 8%; vertical-align: middle; color: #333 !important;" title="{{ __('Grade Point') }}">GP</th>
                                                <th class="text-center" style="width: 12%; vertical-align: middle; color: #333 !important;">{{ __('Remark') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($coursesData as $index => $course)
                                                <tr>
                                                    <td class="text-center text-muted">{{ $index + 1 }}</td>
                                                    <td>
                                                        <strong>{{ $course['subject']->code ?? '--' }}</strong>
                                                    </td>
                                                    <td>{{ $course['subject']->title ?? '--' }}</td>
                                                    <td class="text-center">{{ number_format($course['credit_hour'], 0) }}</td>
                                                    <td class="text-center" style="background: #f8fbff;">
                                                        @if($course['ca_published'])
                                                            {{ number_format($course['attendance_mark'], 1) }}
                                                        @else
                                                            <span class="text-muted" title="{{ __('Not Published') }}">N/P</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center" style="background: #fffcf5;">
                                                        @if($course['ca_published'])
                                                            {{ number_format($course['ca_marks'], 1) }}
                                                        @else
                                                            <span class="text-muted" title="{{ __('Not Published') }}">N/P</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center" style="background: #f8f8ff;">
                                                        @if($course['final_published'])
                                                            {{ number_format($course['exam_marks'], 1) }}
                                                        @else
                                                            <span class="text-muted" title="{{ __('Not Published') }}">N/P</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center font-weight-bold" style="background: #f5f5f5; {{ $course['all_published'] ? ($course['is_passed'] ? 'color: #27ae60;' : 'color: #e74c3c;') : '' }}">
                                                        {{ number_format($course['total_marks'], 1) }}
                                                    </td>
                                                    <td class="text-center">
                                                        @if($course['all_published'])
                                                            {{ number_format($course['grade_point'], 2) }}
                                                        @else
                                                            <span class="text-muted">--</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($course['all_published'])
                                                            @if($course['is_passed'])
                                                                <span class="text-success font-weight-bold"><i class="fas fa-check-circle"></i> Pass</span>
                                                            @else
                                                                <span class="text-danger font-weight-bold"><i class="fas fa-times-circle"></i> Fail</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted" title="{{ __('Awaiting full publication') }}"><i class="fas fa-clock"></i> Pending</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr style="background: #f0f4ff; font-weight: bold;">
                                                <td colspan="3" class="text-right">{{ __('Semester Totals') }}</td>
                                                <td class="text-center">{{ number_format($totalCreditsRegistered, 0) }}</td>
                                                <td colspan="3"></td>
                                                <td class="text-center" style="background: #e8eaf6;">
                                                    @php
                                                        $avgTotal = count($coursesData) > 0 ? collect($coursesData)->avg('total_marks') : 0;
                                                    @endphp
                                                    {{ number_format($avgTotal, 1) }}
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                {{-- Abbreviation Legend --}}
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <strong>ATT</strong> = Attendance &bull;
                                        <strong>CA</strong> = Continuous Assessment (Assignments + Activities + CA Exams) &bull;
                                        <strong>EX</strong> = Final Exam &bull;
                                        <strong>TOT</strong> = ATT + CA + EX &bull;
                                        <strong>CV</strong> = Credit Value &bull;
                                        <strong>GP</strong> = Grade Point
                                    </small>
                                </div>

                                {{-- Transcript CTA Banner --}}
                                <div class="mt-3" style="background: linear-gradient(135deg, #f8f9ff 0%, #eef1ff 100%); border: 1px solid #d5daff; border-left: 4px solid #667eea; border-radius: 6px; padding: 16px 20px;">
                                    <div class="d-flex align-items-start">
                                        <div class="mr-3" style="font-size: 2rem; color: #667eea;">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1" style="color: #4a5568; font-weight: 600;">{{ __('Need your full academic record?') }}</h6>
                                            <p class="mb-2 text-muted" style="font-size: 0.85em;">
                                                {{ __('This page shows results for a single semester only. Your Transcript page provides:') }}
                                            </p>
                                            <ul class="mb-2" style="font-size: 0.82em; color: #718096; padding-left: 18px; list-style: disc;">
                                                <li>{{ __('Complete grade history across all semesters') }}</li>
                                                <li>{{ __('Cumulative GPA (CGPA) and overall academic standing') }}</li>
                                                <li>{{ __('Total credits earned and official grade records') }}</li>
                                                <li>{{ __('Downloadable draft transcript with institutional letterhead') }}</li>
                                            </ul>
                                        </div>
                                        <div class="ml-3 text-center" style="min-width: 130px;">
                                            <a href="{{ route('student.transcript.index') }}" class="btn btn-sm d-block" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; white-space: nowrap; padding: 8px 18px; border-radius: 20px; font-weight: 500;">
                                                <i class="fas fa-arrow-right mr-1"></i> {{ __('View Transcript') }}
                                            </a>
                                            <small class="text-muted d-block mt-1" style="font-size: 0.75em;">{{ __('Full academic record') }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @elseif(request()->isMethod('get') && (request()->has('session') || request()->has('semester') || request()->has('exam_type')))
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-warning" role="alert">
                        {{ __('Please select session, year, semester and exam type to view results.') }}
                    </div>
                </div>
            </div>
        @endif
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="application/json" id="student-semester-options">
<?php echo json_encode($semesterOptions ?? []); ?>
</script>
<script type="application/json" id="student-semester-fallback">
<?php echo json_encode(($semesters ?? collect())->map(function ($item) {
    return [
        'id' => $item->id,
        'title' => $item->title,
        'year' => $item->year,
    ];
})->values()); ?>
</script>
<script type="text/javascript">
"use strict";
(function($){
    if(typeof $ === 'undefined'){
        return;
    }

    $(function(){
        const translationSelect = <?php echo json_encode(__('select')); ?>;
        const translationYear = <?php echo json_encode(__('field_year')); ?>;
        const $year = $('#semester_year');
        const $semester = $('#semester');
        let selectedSemester = <?php echo json_encode($selected_semester ?? ''); ?>;
        let selectedYear = <?php echo json_encode($selected_semester_year ?? ''); ?>;

        const optionsNode = document.getElementById('student-semester-options');
        const fallbackNode = document.getElementById('student-semester-fallback');
        let semesterLookup = {};
        let fallbackSemesters = [];

        try {
            semesterLookup = optionsNode ? JSON.parse(optionsNode.textContent || '{}') : {};
        } catch (error) {
            semesterLookup = {};
        }

        try {
            fallbackSemesters = fallbackNode ? JSON.parse(fallbackNode.textContent || '[]') : [];
        } catch (error) {
            fallbackSemesters = [];
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

        const sortYearKeys = (lookup) => Object.keys(lookup).sort((a, b) => Number(a) - Number(b));

        const deriveYearForSemester = () => {
            if(!selectedSemester){
                return '';
            }
            let derived = '';
            Object.keys(semesterLookup).some((yearKey) => {
                const match = semesterLookup[yearKey].some((item) => String(item.id) === String(selectedSemester));
                if(match){
                    derived = yearKey;
                    return true;
                }
                return false;
            });
            return derived;
        };

        const applyFallbackSemesters = () => {
            resetDropdown($semester, translationSelect);
            if(Array.isArray(fallbackSemesters)){
                fallbackSemesters.forEach((item) => {
                    if(!item){
                        return;
                    }
                    const option = $('<option/>', {
                        value: item.id,
                        text: item.title
                    });
                    if(selectedSemester && String(selectedSemester) === String(item.id)){
                        option.attr('selected', 'selected');
                    }
                    $semester.append(option);
                });
            }
            if(selectedSemester){
                $semester.val(String(selectedSemester));
            }
        };

        const buildYearOptions = () => {
            resetDropdown($year, translationSelect);
            const yearKeys = sortYearKeys(semesterLookup);
            yearKeys.forEach((yearKey) => {
                const option = $('<option/>', {
                    value: yearKey,
                    text: translationYear + ' ' + yearKey
                });
                if(selectedYear && String(selectedYear) === String(yearKey)){
                    option.attr('selected', 'selected');
                }
                $year.append(option);
            });
            if(selectedYear){
                $year.val(String(selectedYear));
            }
        };

        const buildSemesterOptions = (yearKey) => {
            resetDropdown($semester, translationSelect);
            if(yearKey && semesterLookup[yearKey]){
                semesterLookup[yearKey].forEach((item) => {
                    const option = $('<option/>', {
                        value: item.id,
                        text: item.title
                    });
                    if(selectedSemester && String(selectedSemester) === String(item.id)){
                        option.attr('selected', 'selected');
                    }
                    $semester.append(option);
                });
                if(selectedSemester){
                    $semester.val(String(selectedSemester));
                }
                return;
            }

            applyFallbackSemesters();
        };

        const initializeFilters = () => {
            const yearKeys = sortYearKeys(semesterLookup);
            if(yearKeys.length === 0){
                $year.closest('.form-group').addClass('d-none');
                $year.prop('required', false);
                applyFallbackSemesters();
                return;
            }

            $year.closest('.form-group').removeClass('d-none');
            $year.prop('required', true);

            if(!selectedYear){
                const derivedYear = deriveYearForSemester();
                if(derivedYear){
                    selectedYear = derivedYear;
                }
            }

            buildYearOptions();

            if(selectedYear){
                buildSemesterOptions(selectedYear);
            } else {
                resetDropdown($semester, translationSelect);
            }
        };

        initializeFilters();

        $year.on('change', function(){
            selectedYear = $(this).val() || '';
            selectedSemester = '';
            if(selectedYear){
                buildSemesterOptions(selectedYear);
            } else {
                resetDropdown($semester, translationSelect);
            }
        });

        $semester.on('change', function(){
            selectedSemester = $(this).val() || '';
        });
    });
})(window.jQuery);
</script>
@endsection
