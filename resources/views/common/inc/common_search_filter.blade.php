@php
  $filterUid = uniqid('csf_');
  $selectLabel = __('select');
  $yearLabel = __('field_year');
  $allLabel = __('all');
  $selectedProgramValue = isset($selected_program) ? (string)$selected_program : '0';
  $selectedSemesterValue = isset($selected_semester) ? (string)$selected_semester : '0';
  $selectedSectionValue = isset($selected_section) ? (string)$selected_section : '0';
  $selectedYearValue = isset($selected_semester_year) ? (string)$selected_semester_year : '0';
  $includeSemesterAll = isset($include_semester_all) && $include_semester_all;
  $includeSectionAll = isset($include_section_all) && $include_section_all;

  if((empty($selectedYearValue) || $selectedYearValue === '0') && !empty($selectedSemesterValue) && isset($semesters)){
    $matchedSemester = $semesters->firstWhere('id', $selected_semester);
    if(isset($matchedSemester) && !is_null($matchedSemester->year)){
      $selectedYearValue = (string)$matchedSemester->year;
    }
  }

  $semesterOptionsData = $semesterOptions ?? [];
@endphp

<div class="common-search-filter" style="display: contents;" data-filter-id="{{ $filterUid }}"
  data-select-label="{{ $selectLabel }}"
  data-year-label="{{ $yearLabel }}"
  data-all-label="{{ $allLabel }}"
  data-selected-program="{{ $selectedProgramValue }}"
  data-selected-semester="{{ $selectedSemesterValue }}"
  data-selected-year="{{ $selectedYearValue }}"
  data-selected-section="{{ $selectedSectionValue }}"
  data-semester-all="{{ $includeSemesterAll ? '1' : '0' }}"
  data-section-all="{{ $includeSectionAll ? '1' : '0' }}">
  <div class="form-group col-md-3">
    <label for="faculty-{{ $filterUid }}">{{ __('field_faculty') }} <span>*</span></label>
    <select class="form-control faculty common-faculty" name="faculty" id="faculty-{{ $filterUid }}" required>
      <option value="">{{ $selectLabel }}</option>
      @if(isset($faculties))
      @foreach($faculties->sortBy('title') as $faculty)
      <option value="{{ $faculty->id }}" @if($selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
      @endforeach
      @endif
    </select>

    <div class="invalid-feedback">
      {{ __('required_field') }} {{ __('field_faculty') }}
    </div>
  </div>
  <div class="form-group col-md-3">
    <label for="program-{{ $filterUid }}">{{ __('field_program') }} <span>*</span></label>
    <select class="form-control program common-program" name="program" id="program-{{ $filterUid }}" required>
      <option value="">{{ $selectLabel }}</option>
      @if(isset($programs))
      @foreach($programs->sortBy('title') as $program)
      <option value="{{ $program->id }}" @if($selected_program == $program->id) selected @endif>{{ $program->title }}</option>
      @endforeach
      @endif
    </select>

    <div class="invalid-feedback">
      {{ __('required_field') }} {{ __('field_program') }}
    </div>
  </div>
  <div class="form-group col-md-3">
    <label for="session-{{ $filterUid }}">{{ __('field_session') }} <span>*</span></label>
    <select class="form-control session common-session" name="session" id="session-{{ $filterUid }}" required>
      <option value="">{{ $selectLabel }}</option>
      @if(isset($sessions))
      @foreach($sessions->sortByDesc('id') as $session)
      <option value="{{ $session->id }}" @if($selected_session == $session->id) selected @endif>{{ $session->title }}</option>
      @endforeach
      @endif
    </select>

    <div class="invalid-feedback">
      {{ __('required_field') }} {{ __('field_session') }}
    </div>
  </div>
  <div class="form-group col-md-3">
    <label for="semester-year-{{ $filterUid }}">{{ __('field_year') }} <span>*</span></label>
    <select class="form-control common-semester-year" name="semester_year" id="semester-year-{{ $filterUid }}" required>
      <option value="">{{ $selectLabel }}</option>
    </select>

    <div class="invalid-feedback">
    {{ __('required_field') }} {{ __('field_year') }}
    </div>
  </div>
  <div class="form-group col-md-3">
    <label for="semester-{{ $filterUid }}">{{ __('field_semester') }} <span>*</span></label>
    <select class="form-control semester common-semester" name="semester" id="semester-{{ $filterUid }}" required>
      <option value="">{{ $selectLabel }}</option>
    @if($includeSemesterAll)
    <option value="0" @if($selectedSemesterValue === '0') selected @endif>{{ __('all') }}</option>
      @endif
    </select>

    <div class="invalid-feedback">
    {{ __('required_field') }} {{ __('field_semester') }}
    </div>
  </div>
  <div class="form-group col-md-3">
    <label for="section-{{ $filterUid }}">{{ __('field_section') }} <span>*</span></label>
    <select class="form-control section common-section" name="section" id="section-{{ $filterUid }}" required>
      <option value="">{{ $selectLabel }}</option>
    @if($includeSectionAll)
    <option value="0" @if($selectedSectionValue === '0') selected @endif>{{ __('all') }}</option>
      @endif
      @if(isset($sections))
      @foreach($sections->sortBy('title') as $section)
      <option value="{{ $section->id }}" @if($selected_section == $section->id) selected @endif>{{ $section->title }}</option>
      @endforeach
      @endif
    </select>

    <div class="invalid-feedback">
      {{ __('required_field') }} {{ __('field_section') }}
    </div>
  </div>
</div>

<script type="application/json" id="{{ $filterUid }}-semester-data">
  @json($semesterOptionsData)
</script>
<script type="text/javascript">
(function(){
  let loadFallbackAttached = false;

  const bootCommonSearchFilter = function(){
    if(typeof window.jQuery === 'undefined'){
      if(!loadFallbackAttached){
        loadFallbackAttached = true;
        window.addEventListener('load', bootCommonSearchFilter, { once: true });
      }
      return;
    }

    (function($){
      "use strict";

      window.initCommonSearchFilter = window.initCommonSearchFilter || function(filterId){
        const container = document.querySelector('[data-filter-id="' + filterId + '"]');
        if(!container){
          return;
        }
        const $container = $(container);
        const translationSelect = $container.data('selectLabel') || 'Select';
        const translationYear = $container.data('yearLabel') || 'Year';
        let selectedProgram = ($container.data('selectedProgram') || '').toString();
        let selectedSemester = ($container.data('selectedSemester') || '').toString();
        let selectedYear = ($container.data('selectedYear') || '').toString();
        let selectedSection = ($container.data('selectedSection') || '').toString();
        const allowSemesterAll = String($container.data('semesterAll') || '0') === '1';
        const allowSectionAll = String($container.data('sectionAll') || '0') === '1';
        const translationAll = $container.data('allLabel') || 'All';

        if(selectedProgram === '0'){
          selectedProgram = '';
        }
        if(selectedSemester === '0' && !allowSemesterAll){
          selectedSemester = '';
        }
        if(selectedYear === '0'){
          selectedYear = '';
        }
        if(selectedSection === '0' && !allowSectionAll){
          selectedSection = '';
        }

        const dataNode = document.getElementById(filterId + '-semester-data');
        let semesterLookup = {};
        if(dataNode){
          try {
            semesterLookup = JSON.parse(dataNode.textContent || '{}');
          } catch (error) {
            semesterLookup = {};
          }
        }

        const $faculty = $container.find('.common-faculty');
        const $program = $container.find('.common-program');
        const $session = $container.find('.common-session');
        const $year = $container.find('.common-semester-year');
        const $semester = $container.find('.common-semester');
        const $section = $container.find('.common-section');

        const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
        if(csrfToken){
          $.ajaxSetup({
            headers: {
              'X-CSRF-TOKEN': csrfToken
            }
          });
        }

        const resetDropdown = ($element, placeholder = translationSelect, includeAll = false) => {
          if(!$element || !$element.length){
            return;
          }
          $element.empty();
          $element.append($('<option/>', {
            value: '',
            text: placeholder
          }));
          if(includeAll){
            $element.append($('<option/>', {
              value: '0',
              text: translationAll
            }));
          }
        };

        const sortYearKeys = (lookup) => {
          return Object.keys(lookup).sort((a, b) => Number(a) - Number(b));
        };

        const buildYearOptions = (lookup, selected = '') => {
          resetDropdown($year);
          sortYearKeys(lookup).forEach((yearKey) => {
            const option = $('<option/>', {
              value: yearKey,
              text: translationYear + ' ' + yearKey
            });
            if(selected && String(selected) === String(yearKey)){
              option.attr('selected', 'selected');
            }
            $year.append(option);
          });
        };

        const buildSemesterOptions = (lookup, yearKey, selected = '') => {
          resetDropdown($semester, translationSelect, allowSemesterAll);
          if(!yearKey || !lookup[yearKey]){
            return;
          }
          lookup[yearKey].forEach((item) => {
            const option = $('<option/>', {
              value: item.id,
              text: item.title
            });
            if(selected && String(selected) === String(item.id)){
              option.attr('selected', 'selected');
            }
            $semester.append(option);
          });
          if(allowSemesterAll && selected === '0'){
            $semester.val('0');
          }
        };

        const deriveYearForSemester = (lookup, semesterId) => {
          if(!semesterId){
            return '';
          }
          let derivedYear = '';
          Object.keys(lookup).some((yearKey) => {
            const match = lookup[yearKey].some((item) => String(item.id) === String(semesterId));
            if(match){
              derivedYear = yearKey;
              return true;
            }
            return false;
          });
          return derivedYear;
        };

        const transformSemesterResponse = (rows) => {
          const grouped = {};
          if(Array.isArray(rows)){
            rows.forEach((item) => {
              if(item && item.year !== null && item.year !== undefined){
                const yearKey = item.year;
                if(!grouped[yearKey]){
                  grouped[yearKey] = [];
                }
                grouped[yearKey].push({
                  id: item.id,
                  title: item.title
                });
              }
            });
            Object.keys(grouped).forEach((key) => {
              grouped[key].sort((a, b) => Number(a.id) - Number(b.id));
            });
          }
          return grouped;
        };

        const refreshSectionOptions = (items) => {
          resetDropdown($section, translationSelect, allowSectionAll);
          if(Array.isArray(items)){
            items.forEach((item) => {
              const option = $('<option/>', {
                value: item.id,
                text: item.title
              });
              if(selectedSection && String(selectedSection) === String(item.id)){
                option.attr('selected', 'selected');
              }
              $section.append(option);
            });
          }
          if(allowSectionAll && selectedSection === '0'){
            $section.val('0');
          }
        };

        const fetchSections = (semesterId, programId) => {
          if(!semesterId || !programId){
            return;
          }
          $.post("{{ route('filter-section') }}", {
            semester: semesterId,
            program: programId
          }, function(response){
            refreshSectionOptions(response);
          });
        };

        const fetchSessions = (programId) => {
          $.post("{{ route('filter-session') }}", {
            program: programId
          }, function(response){
            resetDropdown($session);
            if(Array.isArray(response)){
              response.forEach((item) => {
                $session.append($('<option/>', {
                  value: item.id,
                  text: item.title
                }));
              });
            }
          });
        };

        const fetchSemesters = (programId) => {
          $.post("{{ route('filter-semester') }}", {
            program: programId
          }, function(response){
            semesterLookup = transformSemesterResponse(response);
            selectedYear = '';
            selectedSemester = '';
            buildYearOptions(semesterLookup, '');
            buildSemesterOptions(semesterLookup, '', '');
          });
        };

        const resetAfterProgramChange = () => {
          resetDropdown($session);
          resetDropdown($year);
          resetDropdown($semester, translationSelect, allowSemesterAll);
          resetDropdown($section, translationSelect, allowSectionAll);
          selectedYear = '';
          selectedSemester = '';
          selectedSection = '';
        };

        const initialYear = selectedYear || deriveYearForSemester(semesterLookup, selectedSemester);
        if(initialYear && initialYear !== '0'){
          selectedYear = initialYear;
        }

        buildYearOptions(semesterLookup, selectedYear);
        buildSemesterOptions(semesterLookup, selectedYear, selectedSemester);

        if(selectedProgram && selectedSemester && !$section.children('option[value!=""]').length){
          fetchSections(selectedSemester, selectedProgram);
        }

        $faculty.on('change', function(e){
          e.preventDefault();
          const facultyId = $(this).val();
          resetAfterProgramChange();
          selectedProgram = '';
          if(!facultyId){
            return;
          }
          $.post("{{ route('filter-program') }}", {
            faculty: facultyId
          }, function(response){
            resetDropdown($program);
            if(Array.isArray(response)){
              response.forEach((item) => {
                $program.append($('<option/>', {
                  value: item.id,
                  text: item.title
                }));
              });
            }
          });
        });

        $program.on('change', function(e){
          e.preventDefault();
          const programId = $(this).val();
          selectedProgram = programId || '';
          resetAfterProgramChange();
          if(!programId){
            return;
          }
          fetchSessions(programId);
          fetchSemesters(programId);
        });

        $year.on('change', function(e){
          e.preventDefault();
          selectedYear = $(this).val() || '';
          selectedSemester = '';
          selectedSection = '';
          buildSemesterOptions(semesterLookup, selectedYear, '');
          resetDropdown($section, translationSelect, allowSectionAll);
        });

        $semester.on('change', function(e){
          e.preventDefault();
          selectedSemester = $(this).val() || '';
          selectedSection = '';
          resetDropdown($section, translationSelect, allowSectionAll);
          if(selectedSemester && selectedProgram){
            fetchSections(selectedSemester, selectedProgram);
          }
        });

        $section.on('change', function(){
          selectedSection = $(this).val() || '';
        });
      };

      $(function(){
        window.initCommonSearchFilter('{{ $filterUid }}');
      });
    })(window.jQuery);
  };

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', bootCommonSearchFilter);
  } else {
    bootCommonSearchFilter();
  }
})();
</script>
