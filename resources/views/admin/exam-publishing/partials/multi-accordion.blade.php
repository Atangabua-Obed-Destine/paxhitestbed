{{--
  One semester's Faculty -> Programme listing in multi-programme mode.
  $faculty_groups, $block_key (DOM id prefix, '' when the page shows one
  semester), $block_semester_id, $block_semester_title.
--}}
            <!-- Faculty → Program Accordion Groups -->
            <div class="col-sm-12">
                <div class="accordion faculty-accordion" id="facultyAccordion{{ $block_key }}">
                    @foreach($faculty_groups as $facultyId => $fg)
                    @php
                        $fac = $fg['faculty'];
                        $facPrograms = $fg['programs'];
                        $facCourseCount = 0;
                        $facStudentCount = 0;
                        $facPublished = 0;
                        $facTotal = 0;
                        foreach ($facPrograms as $pg) {
                            $facCourseCount += count($pg['subjects']);
                            $facStudentCount += $pg['total_students'];
                            foreach ($pg['subject_states'] as $ss) {
                                $facTotal++;
                                if (($ss->workflow_state ?? 'draft') === 'published') $facPublished++;
                            }
                        }
                    @endphp
                    <div class="card mb-2 border-dark">
                        <div class="card-header bg-dark text-white py-2" id="facultyHead{{ $block_key }}{{ $facultyId }}" style="cursor: pointer;"
                             data-bs-toggle="collapse" data-bs-target="#facultyBody{{ $block_key }}{{ $facultyId }}" aria-expanded="true">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-university mr-1"></i> {{ $fac->title }}
                                    <span class="badge badge-light ml-2">{{ count($facPrograms) }} {{ __('Programs') }}</span>
                                </h5>
                                <div>
                                    <span class="badge badge-info">{{ $facCourseCount }} Courses</span>
                                    <span class="badge badge-primary">{{ number_format($facStudentCount) }} Students</span>
                                    <span class="badge badge-success">{{ $facPublished }}/{{ $facTotal }} Published</span>
                                    <i class="fas fa-chevron-down ml-2 accordion-arrow"></i>
                                </div>
                            </div>
                        </div>
                        <div id="facultyBody{{ $block_key }}{{ $facultyId }}" class="collapse show" data-bs-parent="#facultyAccordion{{ $block_key }}">
                            <div class="card-body p-2">
                                {{-- Program Sub-Accordion --}}
                                <div class="accordion" id="programAccordion{{ $block_key }}{{ $facultyId }}">
                                    @foreach($facPrograms as $programId => $pg)
                                    @php
                                        $prog = $pg['program'];
                                        $progSubjects = $pg['subjects'];
                                        $progStates = $pg['subject_states'];
                                        $progStats = $pg['subject_stats'];
                                        $progStudents = $pg['total_students'];
                                        $progPublished = collect($progStates)->where('workflow_state', 'published')->count();
                                        $progDraft = collect($progStates)->where('workflow_state', 'draft')->count();
                                        $isFinalForProg = $pg['is_final_exam'];
                                        $caPublishedForProg = $pg['ca_published'] ?? true;
                                        $caStatusForProg = $pg['ca_publishing_status'] ?? [];
                                    @endphp
                                    <div class="card mb-1 border-secondary">
                                        <div class="card-header bg-light py-2" id="programHead{{ $block_key }}{{ $facultyId }}_{{ $programId }}" style="cursor: pointer;"
                                             data-bs-toggle="collapse" data-bs-target="#programBody{{ $block_key }}{{ $facultyId }}_{{ $programId }}" aria-expanded="false">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-graduation-cap mr-1 text-primary"></i> 
                                                    {{ $prog->title }}
                                                    @if($prog->short_code)
                                                    <small class="text-muted">({{ $prog->short_code }})</small>
                                                    @endif
                                                </h6>
                                                <div>
                                                    <span class="badge badge-secondary">{{ count($progSubjects) }} Courses</span>
                                                    <span class="badge badge-primary">{{ $progStudents }} Students</span>
                                                    @if($progPublished === count($progSubjects) && count($progSubjects) > 0)
                                                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> All Published</span>
                                                    @elseif($progPublished > 0)
                                                        <span class="badge badge-warning text-dark">{{ $progPublished }}/{{ count($progSubjects) }} Published</span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ $progDraft }} Draft</span>
                                                    @endif
                                                    <i class="fas fa-chevron-down ml-1 accordion-arrow text-muted"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="programBody{{ $block_key }}{{ $facultyId }}_{{ $programId }}" class="collapse">
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover table-sm mb-0 multi-courses-table">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th class="text-center" style="width: 35px;">
                                                                    <input type="checkbox" class="form-check-input program-check-all" data-program="{{ $programId }}" data-faculty="{{ $facultyId }}" data-semester="{{ $block_semester_id }}">
                                                                </th>
                                                                <th style="width: 80px;">{{ __('Code') }}</th>
                                                                <th>{{ __('Course Title') }}</th>
                                                                <th class="text-center" style="width: 50px;">CV</th>
                                                                <th class="text-center" style="width: 80px;">{{ __('Students') }}</th>
                                                                <th class="text-center" style="width: 80px;">{{ __('With Marks') }}</th>
                                                                <th class="text-center" style="width: 60px;">{{ __('Pass') }}</th>
                                                                <th class="text-center" style="width: 60px;">{{ __('Fail') }}</th>
                                                                <th class="text-center" style="width: 70px;">{{ __('Avg') }}</th>
                                                                @if($isFinalForProg)
                                                                <th class="text-center" style="width: 80px;">{{ __('CA') }}</th>
                                                                @endif
                                                                <th class="text-center" style="width: 110px;">{{ __('State') }}</th>
                                                                <th class="text-center no-print" style="width: 160px;">{{ __('Actions') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($progSubjects as $subject)
                                                            @php
                                                                $state = $progStates[$subject->id] ?? null;
                                                                $stats = $progStats[$subject->id] ?? [];
                                                                $currentState = $state ? $state->workflow_state : 'draft';
                                                                $badge = $state ? $state->getStateBadge() : ['class' => 'badge-secondary', 'icon' => 'fa-pencil-alt', 'text' => 'Draft'];
                                                                $hasMarks = ($stats['with_marks'] ?? 0) > 0;
                                                                $caStatus = $isFinalForProg ? ($caStatusForProg[$subject->id] ?? false) : true;
                                                            @endphp
                                                            <tr class="{{ !$hasMarks ? 'table-light text-muted' : '' }}" 
                                                                data-state="{{ $currentState }}" 
                                                                data-subject-id="{{ $subject->id }}"
                                                                data-program-id="{{ $programId }}"
                                                                data-semester-id="{{ $block_semester_id }}"
                                                                data-has-marks="{{ $hasMarks ? '1' : '0' }}">
                                                                <td class="text-center">
                                                                    <input type="checkbox" class="form-check-input multi-course-checkbox"
                                                                        value="{{ $state ? $state->id : '' }}"
                                                                        data-state="{{ $currentState }}"
                                                                        data-program-id="{{ $programId }}"
                                                                        data-faculty-id="{{ $facultyId }}"
                                                                        data-semester-id="{{ $block_semester_id }}"
                                                                        data-semester-title="{{ $block_semester_title }}"
                                                                        data-subject-code="{{ $subject->code }}"
                                                                        data-group-key="{{ $subject->course_group_id ? 'g'.$subject->course_group_id : '' }}"
                                                                        {{ !$hasMarks || !$state ? 'disabled' : '' }}>
                                                                </td>
                                                                <td><span class="badge badge-dark">{{ $subject->code }}</span>@if($subject->course_group_id) <span class="badge badge-info" title="{{ __('In a course group: ticking it ticks the same course in the other programmes') }}"><i class="fas fa-link"></i></span>@endif</td>
                                                                <td>{{ $subject->title ?? $subject->subject_name }}</td>
                                                                <td class="text-center">{{ $subject->credit_hour }}</td>
                                                                <td class="text-center">{{ $stats['total'] ?? 0 }}</td>
                                                                <td class="text-center">
                                                                    @if($hasMarks)
                                                                    <span class="badge badge-info">{{ $stats['with_marks'] }}</span>
                                                                    @else
                                                                    <span class="badge badge-danger">0</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center text-success">{{ $stats['passed'] ?? 0 }}</td>
                                                                <td class="text-center text-danger">{{ $stats['failed'] ?? 0 }}</td>
                                                                <td class="text-center">
                                                                    @if(isset($stats['average']) && $stats['average'] !== '-')
                                                                        {{ $stats['average'] }}/{{ $stats['contribution'] ?? 100 }}
                                                                    @else - @endif
                                                                </td>
                                                                @if($isFinalForProg)
                                                                <td class="text-center">
                                                                    @if($caStatus)
                                                                    <span class="badge badge-success"><i class="fas fa-check"></i></span>
                                                                    @else
                                                                    <span class="badge badge-danger"><i class="fas fa-times"></i></span>
                                                                    @endif
                                                                </td>
                                                                @endif
                                                                <td class="text-center">
                                                                    <span class="badge {{ $badge['class'] }}">
                                                                        <i class="fas {{ $badge['icon'] }}"></i> {{ $badge['text'] }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-center no-print">
                                                                    @if($state && $hasMarks)
                                                                        @php $nextStates = $state->getNextStates(); $canPublish = !$isFinalForProg || $caStatus; @endphp
                                                                        @foreach($nextStates as $nextState)
                                                                            @if($nextState === 'published' && !$canPublish)
                                                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="CA must be published first">
                                                                                    <i class="fas fa-lock"></i>
                                                                                </button>
                                                                            @else
                                                                                @can('exam-publishing-' . ($nextState === 'submitted' ? 'submit' : ($nextState === 'checked' ? 'check' : ($nextState === 'approved' ? 'approve' : 'publish'))))
                                                                                <button type="button" class="btn btn-sm btn-{{ $nextState === 'published' ? 'success' : 'primary' }} transition-btn"
                                                                                    data-state-id="{{ $state->id }}"
                                                                                    data-target-state="{{ $nextState }}"
                                                                                    data-subject-code="{{ $subject->code }}"
                                                                                    title="Transition to {{ ucfirst($nextState) }}">
                                                                                    <i class="fas fa-{{ $nextState === 'submitted' ? 'paper-plane' : ($nextState === 'checked' ? 'check' : ($nextState === 'approved' ? 'thumbs-up' : 'globe')) }}"></i>
                                                                                </button>
                                                                                @endcan
                                                                            @endif
                                                                        @endforeach
                                                                        <button type="button" class="btn btn-sm btn-outline-info history-btn"
                                                                            data-state-id="{{ $state->id }}"
                                                                            data-subject-code="{{ $subject->code }}"
                                                                            title="View History">
                                                                            <i class="fas fa-history"></i>
                                                                        </button>
                                                                    @elseif(!$hasMarks)
                                                                        <span class="text-muted small">No marks</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
