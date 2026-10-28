{{--
  The courses this user teaches, as a way straight into taking attendance.

  What changed, and why it was worth changing:

  Every course was rendered TWICE — once as a card and once as a table row —
  with its own set of four dropdowns each time. On this install that is 283
  courses, 566 selects and 394 KB of data-semesters JSON, and it made this
  page 4.7 MB of which 96% was this panel, which is collapsed when the page
  opens. The button that would have swapped card view for table view was
  bound to `.view-toggle-btn`, a class no element on the page carried, so the
  table half could never be shown at all.

  So: one element per course, laid out as a card or as a row by a class on the
  container, and the buttons do the swapping. Same controls, same ids, same
  handlers, half the markup.

  A filter box is new. It is not decoration at 283 courses — it is the
  difference between this panel being usable and being scrolled past.

  Every id the shared handlers reach for is unchanged: year_<key>,
  semester_<key>, section_<key>, examtype_<key>, status_<key>, where <key> is
  "<programme index>_<subject index>", and .quick-year / .quick-semester /
  .quick-section / .quick-exam-type / .quick-take-attendance still carry the
  same data attributes. SemesterYearSelect.bindCards() binds .quick-year.

  Expects $myAssignedCourses, $examTypes, $currentSession.
--}}

@php
    $courseCount = $myAssignedCourses->sum(fn ($p) => $p['subjects']->count());
@endphp

<div class="col-sm-12">
    <div class="exm-panel">
        <div class="exm-actions" style="border-top:0;border-bottom:1px solid var(--rule-soft)">
            <div>
                <span class="exm-label">{{ __('My Assigned Courses') }}</span>
                <div class="exm-num" style="font-size:.86rem;color:var(--body);margin-top:.1rem">
                    {{ __(':courses courses in :programmes programmes', [
                           'courses' => number_format($courseCount),
                           'programmes' => $myAssignedCourses->count(),
                       ]) }}
                    @if(isset($currentSession) && $currentSession)
                        · {{ $currentSession->title }}
                    @endif
                </div>
            </div>

            <div class="exm-right">
                <button class="exm-btn" type="button"
                        data-bs-toggle="collapse" data-bs-target="#myCoursesCollapse"
                        aria-expanded="false" aria-controls="myCoursesCollapse">
                    <i class="fas fa-chevron-down" id="collapseIcon"></i>
                    {{ __('Show') }}
                </button>
            </div>
        </div>

        <div class="collapse" id="myCoursesCollapse">
            <div style="padding:1rem 1.1rem">

                <div class="exm-actions" style="border-top:0;padding:0 0 .9rem">
                    <div style="min-width:220px;flex:1 1 260px">
                        <label for="courseSearch" class="exm-label" style="display:block;margin-bottom:.2rem">
                            {{ __('Find a course') }}
                        </label>
                        <input type="search" class="form-control" id="courseSearch" autocomplete="off"
                               placeholder="{{ __('Code or title') }}"
                               style="border:1px solid var(--rule);border-radius:6px;font-size:.86rem">
                    </div>

                    <div style="min-width:200px;flex:0 1 240px">
                        <label for="quickProgramSelector" class="exm-label" style="display:block;margin-bottom:.2rem">
                            {{ trans_choice('module_program', 1) }}
                        </label>
                        <select class="form-control" id="quickProgramSelector"
                                style="border:1px solid var(--rule);border-radius:6px;font-size:.86rem">
                            <option value="all">{{ __('All Programs') }} ({{ $myAssignedCourses->count() }})</option>
                            @foreach($myAssignedCourses as $idx => $programData)
                            <option value="{{ $idx }}">{{ $programData['program_title'] }} ({{ $programData['subjects']->count() }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="exm-right">
                        {{-- data-view is what the handler reads; the class is what it binds. --}}
                        <span class="exm-seg" role="group" aria-label="{{ __('Layout') }}">
                            <button type="button" class="view-toggle-btn active" id="viewCards" data-view="card"
                                    title="{{ __('Cards') }}" aria-pressed="true">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button type="button" class="view-toggle-btn" id="viewTable" data-view="table"
                                    title="{{ __('Rows') }}" aria-pressed="false">
                                <i class="fas fa-list"></i>
                            </button>
                        </span>
                    </div>
                </div>

                <div id="coursesContainer">
                    @foreach($myAssignedCourses as $programIndex => $programData)
                    <div class="program-section exm-group" data-program-index="{{ $programIndex }}">
                        <div class="exm-group-head">
                            <span class="exm-label">{{ $programData['program_title'] }}</span>
                            <span class="exm-tag exm-tag-flat exm-num">{{ $programData['subjects']->count() }}</span>
                        </div>

                        <div class="exm-courses is-cards">
                            @foreach($programData['subjects'] as $subjectIndex => $subjectData)
                            @php $uniqueId = $programIndex . '_' . $subjectIndex; @endphp

                            <article class="exm-course"
                                     data-search="{{ Str::lower($subjectData['subject_code'] . ' ' . $subjectData['subject_title']) }}">
                                <div>
                                    <div class="exm-course-top">
                                        <span class="exm-course-code">{{ $subjectData['subject_code'] }}</span>
                                        <span class="exm-tag exm-tag-flat">
                                            {{ $subjectData['sections']->count() }} {{ trans_choice('section|sections', $subjectData['sections']->count()) }}
                                        </span>
                                    </div>
                                    <h6 title="{{ $subjectData['subject_title'] }}">{{ $subjectData['subject_title'] }}</h6>
                                    <div class="exm-course-meta">{{ $programData['program_code'] }}</div>
                                </div>

                                <div class="exm-course-set">
                                    <div>
                                        <label for="year_{{ $uniqueId }}">{{ __('Year') }}</label>
                                        <select class="form-control quick-year" id="year_{{ $uniqueId }}"
                                                data-unique="{{ $uniqueId }}">
                                            @foreach($subjectData['years'] as $yr)
                                            <option value="{{ $yr }}" {{ $yr == $subjectData['first_year'] ? 'selected' : '' }}>{{ $yr }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="semester_{{ $uniqueId }}">{{ __('Semester') }}</label>
                                        <select class="form-control quick-semester" id="semester_{{ $uniqueId }}"
                                                data-subject="{{ $subjectData['subject_id'] }}"
                                                data-semesters="{{ $subjectData['semesters']->toJson() }}">
                                            @foreach($subjectData['semesters'] as $sem)
                                            <option value="{{ $sem->id }}"
                                                    data-year="{{ $sem->year }}"
                                                    data-is-resit="{{ $sem->is_resit ? '1' : '0' }}"
                                                    {{ $sem->id == $subjectData['first_semester_id'] ? 'selected' : '' }}>
                                                {{ $sem->title }}@if($sem->is_resit) ({{ __('Resit') }})@endif
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="section_{{ $uniqueId }}">{{ __('Section') }}</label>
                                        @if($subjectData['sections']->count() > 1)
                                        <select class="form-control quick-section" id="section_{{ $uniqueId }}">
                                            <option value="0">{{ __('All Sections') }}</option>
                                            @foreach($subjectData['sections'] as $secIdx => $sec)
                                            <option value="{{ $sec->id }}" {{ $secIdx === 0 ? 'selected' : '' }}>{{ $sec->title }}</option>
                                            @endforeach
                                        </select>
                                        @else
                                        <input type="text" class="form-control" value="{{ $subjectData['first_section_title'] }}" disabled>
                                        <input type="hidden" id="section_{{ $uniqueId }}" value="{{ $subjectData['first_section_id'] ?? 0 }}">
                                        @endif
                                    </div>
                                    <div>
                                        <label for="examtype_{{ $uniqueId }}">{{ __('Exam Type') }} *</label>
                                        <select class="form-control quick-exam-type" id="examtype_{{ $uniqueId }}" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($examTypes as $et)
                                            <option value="{{ $et->id }}">{{ $et->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <button type="button"
                                            class="exm-btn exm-btn-main exm-course-go quick-take-attendance"
                                            data-faculty="{{ $programData['faculty_id'] }}"
                                            data-program="{{ $programData['program_id'] }}"
                                            data-session="{{ $currentSession->id ?? '' }}"
                                            data-subject="{{ $subjectData['subject_id'] }}"
                                            data-unique="{{ $uniqueId }}">
                                        <i class="fas fa-user-check"></i> {{ __('Take Attendance') }}
                                    </button>

                                    {{-- Filled in by checkAttendanceStatus(); ships hidden. --}}
                                    <div class="attendance-status-indicator" id="status_{{ $uniqueId }}" style="display: none;">
                                        <div class="status-content">
                                            <div class="status-info">
                                                <i class="status-icon"></i>
                                                <span class="status-message"></span>
                                            </div>
                                            <div class="status-details">
                                                <span class="present-count" title="{{ __('attendance_present') }}"></span>
                                                <span class="absent-count" title="{{ __('attendance_absent') }}"></span>
                                                <span class="pending-count" title="{{ __('Pending') }}"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="exm-empty" id="courseSearchEmpty" style="display:none">
                    <i class="fas fa-search"></i>
                    <p>{{ __('no_result_found') }}</p>
                    <small>{{ __('No course matches what you typed.') }}</small>
                </div>
            </div>
        </div>
    </div>
</div>
