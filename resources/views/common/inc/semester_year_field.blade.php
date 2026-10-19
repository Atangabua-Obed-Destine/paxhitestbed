{{--
  The Year select that goes in front of a Semester select.

  Included by the legacy filter partials, which each render their own
  <select name="semester" id="semester" class="semester">. Those names are left
  alone deliberately: around 25 host screens bind their own JavaScript to them,
  so renaming the control to match the newer shared filter would break them in
  ways no structural test would catch.

  The grouping is worked out here from $semesters rather than demanded from the
  controller. Every host already passes $semesters, so this needs no change in
  the twenty-odd controllers behind these screens; a host that has already done
  the work and passes $semesterOptions has that used instead.

  Expects: $semesters (collection). Optional: $semesterOptions,
  $selected_semester, $selected_semester_year, $year_target (the selector of
  the semester select to drive, default '.semester'), $include_year_all.
--}}
@php
  $syUid = uniqid('syf_');
  $syTarget = $year_target ?? '.semester';
  $syAll = isset($include_year_all) && $include_year_all;

  $syLookup = $semesterOptions
    ?? \App\Services\Academic\SemesterYearOptions::fromCollection(collect($semesters ?? []));

  $syOrphans = [];

  foreach (\App\Services\Academic\SemesterYearOptions::orphans(collect($semesters ?? [])) as $syRow) {
      $syOrphans[] = ['id' => $syRow->id, 'title' => $syRow->title];
  }

  $syConfig = [
      'lookup' => $syLookup,
      'orphans' => $syOrphans,
      'selectedYear' => isset($selected_semester_year) ? (string) $selected_semester_year : '',
      'selectedSemester' => isset($selected_semester) ? (string) $selected_semester : '',
      'target' => $syTarget,
      'includeYearAll' => $syAll,
      'labels' => [
          'select' => __('select'),
          'year' => __('field_year'),
          'all' => __('all'),
      ],
  ];
@endphp

<div class="form-group col-md-3">
  <label for="semester-year-{{ $syUid }}">{{ __('field_year') }} <span>*</span></label>
  <select class="form-control semester-year" name="semester_year" id="semester-year-{{ $syUid }}" required>
    <option value="">{{ __('select') }}</option>
  </select>

  <div class="invalid-feedback">
    {{ __('required_field') }} {{ __('field_year') }}
  </div>
</div>

<script type="application/json" id="{{ $syUid }}-data">{!! json_encode($syConfig) !!}</script>

<script type="text/javascript">
(function () {
  "use strict";

  var start = function () {
    if (typeof window.jQuery === 'undefined' || !window.SemesterYearSelect) {
      window.addEventListener('load', start, { once: true });
      return;
    }

    var $ = window.jQuery;
    var node = document.getElementById('{{ $syUid }}-data');

    if (!node) {
      return;
    }

    var config = {};

    try {
      config = JSON.parse(node.textContent || '{}');
    } catch (e) {
      return;
    }

    var $year = $('#semester-year-{{ $syUid }}');
    var $semester = $(config.target);

    if (!$semester.length) {
      return;
    }

    // The handle is published on the semester select so the partial's own
    // programme-change AJAX can hand it a fresh list without reaching in here.
    var handle = window.SemesterYearSelect.attach({
      $year: $year,
      $semester: $semester,
      lookup: config.lookup || {},
      orphans: config.orphans || [],
      selectLabel: (config.labels || {}).select || 'Select',
      yearLabel: (config.labels || {}).year || 'Year',
      allLabel: (config.labels || {}).all || 'All',
      // These filters offer "All" as a real choice, so it has to stay
      // reachable without first picking a year - that is what keeps the
      // all-semesters reports working.
      includeSemesterAll: $semester.find('option[value="0"]').length > 0,
      includeYearAll: !!config.includeYearAll,
      selectedYear: config.selectedYear,
      selectedSemester: config.selectedSemester
    });

    $semester.data('semesterYearHandle', handle);
  };

  start();
})();
</script>
