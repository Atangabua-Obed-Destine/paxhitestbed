@php
  $filterUid = uniqid('ssf_');
  $allLabel = __('all');
  $selectedProgramValue = isset($selected_program) ? (string)$selected_program : '0';
  $selectedSemesterValue = isset($selected_semester) ? (string)$selected_semester : '0';
  $selectedSectionValue = isset($selected_section) ? (string)$selected_section : '0';
  $selectedYearValue = isset($selected_semester_year) ? (string)$selected_semester_year : '0';
  $includeSemesterAll = true;
  $includeSectionAll = true;
  $semesterOptionsData = $semesterOptions ?? [];
@endphp

@include('common.inc.common_search_filter', [
  'faculties' => $faculties ?? collect(),
  'programs' => $programs ?? collect(),
  'sessions' => $sessions ?? collect(),
  'semesters' => $semesters ?? collect(),
  'sections' => $sections ?? collect(),
  'semesterOptions' => $semesterOptionsData,
  'selected_faculty' => $selected_faculty ?? '0',
  'selected_program' => $selectedProgramValue,
  'selected_session' => $selected_session ?? '0',
  'selected_semester' => $selectedSemesterValue,
  'selected_semester_year' => $selectedYearValue,
  'selected_section' => $selectedSectionValue,
  'include_semester_all' => $includeSemesterAll,
  'include_section_all' => $includeSectionAll,
])