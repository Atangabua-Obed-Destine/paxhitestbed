{{--
  "My Assigned Courses" - the courses this member of staff is scheduled to
  teach this session, so they can go straight to one instead of working the
  faculty / programme / session / semester / section filters every time.

  Expects:
    $assignedCourses  grouped collection from Academic\AssignedCourses::forUser()
    $currentSession   the session those courses belong to
    $assignedAction   route name the action button navigates to
    $assignedLabel    the button's text
  Optional:
    $assignedIcon     font-awesome class for the button (default a pencil)
    $assignedDate     true to include today's date in the link (attendance)

  The Year/Semester pair uses .quick-year and .quick-semester, which the shared
  common/js/semester_year_select module already drives - so this block needs no
  JavaScript of its own beyond the action button.
--}}
@php
  $acTotal = \App\Services\Academic\AssignedCourses::countCourses(collect($assignedCourses ?? []));
  $acIcon = $assignedIcon ?? 'fas fa-edit';
  $acWithDate = isset($assignedDate) && $assignedDate;
@endphp

@if($acTotal > 0)
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center"
       style="background: linear-gradient(90deg,#4680ff,#1b55e2); color:#fff;">
    <h5 class="mb-0 text-white">
      <i class="fas fa-chalkboard-teacher"></i> {{ __('My Assigned Courses') }}
      <small class="ml-2" style="opacity:.85">
        <i class="fas fa-calendar"></i> {{ optional($currentSession)->title }}
        &bull; {{ $acTotal }} {{ __('Total Courses') }}
      </small>
    </h5>
    <button class="btn btn-sm btn-light" type="button" data-toggle="collapse"
            data-bs-toggle="collapse" data-target="#assignedCoursesCollapse"
            data-bs-target="#assignedCoursesCollapse" aria-expanded="true">
      <i class="fas fa-chevron-up" id="assignedCoursesIcon"></i>
    </button>
  </div>

  <div class="collapse show" id="assignedCoursesCollapse">
    <div class="card-body">
      {{-- One programme at a time keeps a long list navigable. --}}
      @if($acTotal > 6)
      <div class="form-group">
        <select class="form-control" id="assignedProgramFilter">
          <option value="">{{ __('All Programs') }} ({{ $acTotal }})</option>
          @foreach($assignedCourses as $programData)
          <option value="ac-prog-{{ $programData['program_id'] }}">
            {{ $programData['program_title'] }} ({{ $programData['subjects']->count() }})
          </option>
          @endforeach
        </select>
      </div>
      @endif

      @foreach($assignedCourses as $programIndex => $programData)
      <div class="assigned-program mb-3" data-program-key="ac-prog-{{ $programData['program_id'] }}">
        <h6 class="text-primary mb-2">
          <i class="fas fa-folder-open"></i> {{ $programData['program_title'] }}
          <span class="badge badge-secondary">{{ $programData['subjects']->count() }} {{ __('courses') }}</span>
        </h6>

        <div class="row">
          @foreach($programData['subjects'] as $subjectIndex => $subjectData)
          @php $acUid = 'ac_' . $programIndex . '_' . $subjectIndex; @endphp
          <div class="col-md-4 col-sm-6 mb-3">
            <div class="border rounded p-3 h-100" style="border-left: 4px solid #4680ff !important;">
              <span class="badge badge-dark">{{ $subjectData['subject_code'] }}</span>
              <h6 class="font-weight-bold text-dark mt-2 mb-2" title="{{ $subjectData['subject_title'] }}">
                {{ Str::limit($subjectData['subject_title'], 35) }}
              </h6>

              <div class="row mb-2">
                <div class="col-6">
                  <label class="small text-muted mb-1">{{ __('Year') }}</label>
                  <select class="form-control form-control-sm quick-year"
                          id="year_{{ $acUid }}" data-unique="{{ $acUid }}">
                    @foreach($subjectData['years'] as $yr)
                    <option value="{{ $yr }}" {{ (string) $yr === (string) $subjectData['first_year'] ? 'selected' : '' }}>
                      {{ __('Year') }} {{ $yr }}
                    </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-6">
                  <label class="small text-muted mb-1">{{ __('Semester') }}</label>
                  <select class="form-control form-control-sm quick-semester"
                          id="semester_{{ $acUid }}"
                          data-semesters="{{ $subjectData['semesters']->toJson() }}">
                    @foreach($subjectData['semesters'] as $sem)
                    <option value="{{ $sem->id }}" data-year="{{ $sem->year }}"
                            data-is-resit="{{ $sem->is_resit ? '1' : '0' }}"
                            {{ $sem->id == $subjectData['first_semester_id'] ? 'selected' : '' }}>
                      {{ $sem->title }}@if($sem->is_resit) (Resit)@endif
                    </option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="mb-2">
                <label class="small text-muted mb-1">{{ __('Section') }}</label>
                @if($subjectData['sections']->count() > 1)
                <select class="form-control form-control-sm" id="section_{{ $acUid }}">
                  <option value="0">{{ __('All Sections') }}</option>
                  @foreach($subjectData['sections'] as $sec)
                  <option value="{{ $sec->id }}">{{ $sec->title }}</option>
                  @endforeach
                </select>
                @else
                <input type="text" class="form-control form-control-sm"
                       value="{{ $subjectData['first_section_title'] }}" disabled>
                <input type="hidden" id="section_{{ $acUid }}" value="{{ $subjectData['first_section_id'] ?? 0 }}">
                @endif
              </div>

              @if($acWithDate)
              <div class="mb-2">
                <label class="small text-muted mb-1">{{ __('field_date') }}</label>
                <input type="date" class="form-control form-control-sm" id="date_{{ $acUid }}"
                       value="{{ $selected_date ?? date('Y-m-d') }}">
              </div>
              @endif

              <button type="button" class="btn btn-sm btn-block assigned-course-go"
                      style="background:#4680ff;color:#fff"
                      data-faculty="{{ $programData['faculty_id'] }}"
                      data-program="{{ $programData['program_id'] }}"
                      data-session="{{ optional($currentSession)->id }}"
                      data-subject="{{ $subjectData['subject_id'] }}"
                      data-unique="{{ $acUid }}"
                      data-url="{{ route($assignedAction) }}"
                      data-with-date="{{ $acWithDate ? '1' : '0' }}">
                <i class="{{ $acIcon }}"></i> {{ $assignedLabel }}
              </button>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>

<script type="text/javascript">
(function () {
  "use strict";

  var start = function () {
    if (typeof window.jQuery === 'undefined') {
      window.addEventListener('load', start, { once: true });
      return;
    }

    var $ = window.jQuery;

    // Namespaced and re-bound each time, so including this block twice on a
    // page cannot fire the handler twice.
    $(document).off('click.assignedCourses').on('click.assignedCourses', '.assigned-course-go', function () {
      var btn = $(this);
      var uid = btn.data('unique');

      var params = {
        faculty: btn.data('faculty'),
        program: btn.data('program'),
        session: btn.data('session'),
        semester: $('#semester_' + uid).val() || 0,
        section: $('#section_' + uid).val() || 0,
        subject: btn.data('subject')
      };

      if (String(btn.data('with-date')) === '1') {
        params.date = $('#date_' + uid).val() || '';
      }

      var query = Object.keys(params)
        .map(function (key) {
          return encodeURIComponent(key) + '=' + encodeURIComponent(params[key] === null ? '' : params[key]);
        })
        .join('&');

      btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
      window.location.href = btn.data('url') + '?' + query;
    });

    $(document).off('change.assignedCourses').on('change.assignedCourses', '#assignedProgramFilter', function () {
      var wanted = $(this).val();
      $('.assigned-program').each(function () {
        $(this).toggle(!wanted || $(this).data('program-key') === wanted);
      });
    });
  };

  start();
})();
</script>
@endif
