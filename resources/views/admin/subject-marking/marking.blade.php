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
                    
                    @php
                        $contribution = 0;
                        $exam_contribution = 0;
                        $con_attendances = 0;
                        $con_assignments = 0;
                        $con_activities = 0;
                    @endphp
                    
                    @if(isset($examTypeContributions) && !empty($examTypeContributions))
                        @foreach($examTypes as $examType)
                            @php
                            $examContribution = $examTypeContributions[$examType->id]->contribution ?? 0;
                            $contribution = $contribution + $examContribution;
                            $exam_contribution = $exam_contribution + $examContribution;
                            @endphp
                        @endforeach
                    @endif
                    
                    @isset($resultContributions)
                    @php
                        $con_attendances = $resultContributions->attendances;
                        $con_assignments = $resultContributions->assignments;
                        $con_activities = $resultContributions->activities;

                        $contribution = $contribution + $con_attendances + $con_assignments + $con_activities;
                    @endphp
                    @endisset

                    @if(isset($selected_subject) && $selected_subject != '0')
                        @if($contribution != 100)
                        <div class="card-block">
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> 
                                {{ __('msg_your_contribution_is_not_correct') }} ({{ __('field_current') }}: {{ round($contribution, 2) }}%)
                                <br>
                                {{ __('msg_mark_distribution_not_configured') }}
                                <a href="{{ route('admin.result-contribution.index') }}" class="alert-link">{{ __('Click here to configure') }}</a>.
                            </div>
                        </div>
                        @endif
                    @endif

                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                @include('common.inc.subject_search_filter')

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>


                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    @if(isset($rows))
                    <div class="card-header">
                        @if(isset($markings))
                        @if(count($markings) > 0)
                        <div class="alert alert-success" role="alert">
                            {{ __('marks_given') }}
                        </div>
                        @else
                        <div class="alert alert-danger" role="alert">
                            {{ __('marks_not_given') }}
                        </div>
                        @endif
                        @endif
                    </div>
                    @endif
                    
                    @isset($rows)
                    @foreach($rows as $row)
                        @php
                        foreach($row->subjectMarks->where('subject_id', $selected_subject) as $check){

                            if($check->student_enroll_id == $row->id){
                                $check_data = $check;
                                break;
                            }
                        }
                        @endphp
                    @endforeach
                    
                    {{-- Publish Date/Time Inputs (outside form for workflow use) --}}
                    <div class="card-block">
                        <div class="row">
                            <div class="form-group col-sm-6 col-md-3">
                                <label for="publish_date">{{ __('field_publish_date') }} <span>*</span></label>
                                <input type="date" class="form-control date" name="publish_date_display" id="publish_date" value="{{ isset($check_data->publish_date) ? (is_string($check_data->publish_date) ? $check_data->publish_date : $check_data->publish_date->format('Y-m-d')) : '' }}">
                                    
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_publish_date') }}
                                </div>
                            </div>

                            <div class="form-group col-sm-6 col-md-3">
                                <label for="publish_time">{{ __('field_publish_time') }} <span>*</span></label>
                                <input type="time" class="form-control time" name="publish_time_display" id="publish_time" value="{{ isset($check_data->publish_time) ? (is_string($check_data->publish_time) ? date('H:i', strtotime($check_data->publish_time)) : $check_data->publish_time->format('H:i')) : '' }}">
                                    
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_publish_time') }}
                                </div>
                            </div>
                        </div>

                        {{-- Bulk Workflow Actions for Exam Types --}}
                        @if(isset($examTypes) && count($examTypes) > 0)
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>{{ __('Workflow Actions by Exam Type') }}</h6>
                                <p class="text-muted small">Submit workflow actions for all students in each exam category</p>
                                
                                @foreach($examTypes as $examType)
                                    @php
                                        // Get course-specific contribution for this exam type
                                        $examContribution = isset($examTypeContributions[$examType->id]) ? 
                                            $examTypeContributions[$examType->id]->contribution : 0;
                                    @endphp
                                    @if($examContribution > 0)
                                        @php
                                            // Get workflow metadata for this exam type (use first student's marking as reference)
                                            $firstMarkingRecord = isset($rows) && count($rows) > 0 ? 
                                                $rows[0]->subjectMarks->firstWhere('subject_id', $selected_subject) : null;
                                            $workflowInfo = ($firstMarkingRecord && !empty($workflowMeta)) ? 
                                                ($workflowMeta[$firstMarkingRecord->id] ?? null) : null;
                                            $examTypeMeta = isset($workflowInfo['exam_types'][$examType->id]) ? 
                                                $workflowInfo['exam_types'][$examType->id] : null;
                                            $examTransitions = $examTypeMeta['transitions'] ?? [];
                                            $examState = $examTypeMeta['state'] ?? 'draft';
                                            $examBadge = $examTypeMeta['badge'] ?? 'secondary';
                                        @endphp
                                        
                                        <div class="d-inline-block mr-3 mb-2">
                                            <span class="badge badge-dark">{{ $examType->title }} ({{ round($examContribution, 2) }}%)</span>
                                            <span class="badge badge-{{ $examBadge }} ml-1">{{ strtoupper($examState) }}</span>
                                            
                                            @if(!empty($examTransitions))
                                                @foreach($examTransitions as $transition)
                                                    <form action="{{ route($route.'.bulk-transition') }}" method="post" class="d-inline bulk-transition-form">
                                                        @csrf
                                                        <input type="hidden" name="state" value="{{ $transition }}">
                                                        <input type="hidden" name="exam_type_id" value="{{ $examType->id }}">
                                                        <input type="hidden" name="subject_id" value="{{ $selected_subject }}">
                                                        <input type="hidden" name="program_id" value="{{ $selected_program }}">
                                                        <input type="hidden" name="session_id" value="{{ $selected_session }}">
                                                        @if($selected_semester && $selected_semester != '0')
                                                        <input type="hidden" name="semester_id" value="{{ $selected_semester }}">
                                                        @endif
                                                        @if($selected_section && $selected_section != '0')
                                                        <input type="hidden" name="section_id" value="{{ $selected_section }}">
                                                        @endif
                                                        <input type="hidden" name="publish_date" class="bulk-publish-date-field" value="">
                                                        <input type="hidden" name="publish_time" class="bulk-publish-time-field" value="">
                                                        <button type="submit" class="btn btn-sm btn-primary ml-1" 
                                                            onclick="return confirm('Transition all students\' {{ $examType->title }} to {{ ucwords(str_replace('_', ' ', $transition)) }}?')">
                                                            {{ ucwords(str_replace('_', ' ', $transition)) }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endisset
                    
                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    
                    {{-- Hidden fields for publish date/time that sync with display fields --}}
                    <input type="hidden" name="publish_date" id="publish_date_hidden" value="">
                    <input type="hidden" name="publish_time" id="publish_time_hidden" value="">


                    @if(isset($rows))
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_exam') }} ({{ round($exam_contribution ?? '', 2) }})</th>
                                        @if(isset($con_attendances) && $con_attendances > 0)
                                        <th>{{ __('field_attendance') }} ({{ round($con_attendances, 2) }})</th>
                                        @endif
                                        @if(isset($con_assignments) && $con_assignments > 0)
                                        <th>{{ __('field_assignment') }} ({{ round($con_assignments, 2) }})</th>
                                        @endif
                                        @if(isset($con_activities) && $con_activities > 0)
                                        <th>{{ __('field_activities') }} ({{ round($con_activities, 2) }})</th>
                                        @endif
                                        <th>{{ __('field_total_marks') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('Exam Details') }}</th>
                                        <th>{{ __('Publish Control') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <input type="hidden" name="students[]" value="{{ $row->id }}">
                                    <input type="hidden" name="subjects[]" value="{{ $subject->id }}">
                                    <tr>
                                        <td>
                                            @isset($row->student->student_id)
                                            <a href="{{ route('admin.student.show', $row->student->id) }}">
                                            <strong style="color: #667eea;">#{{ $row->matricule ?? $row->student->student_id ?? '' }}</strong>
                                            @if($row->program)
                                                <br>
                                                <span class="badge" style="background: {{ $row->program->academic_level == 'M' ? '#f5576c' : ($row->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px;">
                                                    {{ $row->program->academic_level == 'A' ? 'UG' : ($row->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                            </a>
                                            @endisset
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>
                                        @php
                                            $exam_marks = 0;
                                            $contributeOfMarks = 0;
                                            $max_marks = $exam_contribution ?? 0;
                                        @endphp
                                        @foreach($row->exams->where('subject_id', $selected_subject) as $exam)
                                        @if($exam->attendance == 1 && $exam->student_enroll_id == $row->id && $exam->contribution > 0)

                                        @php
                                            $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                                            $contributeOfMarks = $contributeOfMarks + (($percentOfMarks / 100) * $exam->contribution);
                                            $exam_marks = $contributeOfMarks;
                                        @endphp
                                            
                                        @endif
                                        @endforeach

                                        @php
                                        $mark = '0';
                                        $attend = '0';
                                        $assignment = '0';
                                        $activity = '0';

                                        //Subject Marks
                                        foreach($row->subjectMarks->where('subject_id', $selected_subject) as $marking){
                                        if($marking->student_enroll_id == $row->id){
                                         $mark = $marking->exam_marks;
                                         $attend = $marking->attendances;
                                         $assignment = $marking->assignments;
                                         $activity = $marking->activities;
                                         break;
                                        }
                                        }
                                        @endphp
                                        <input type="text" class="form-control exam_marks autonumber" name="exam_marks[]" id="exam_marks" value="{{ round($exam_marks ?? $mark, 2) }}" 
                                        style="width: 80px;" data-v-max="{{ $max_marks }}" data-v-min="0" data-sync="total-{{ $row->id }}" onkeyup="marksCalculator('total', {{ $row->id }})" readonly required {{ $contribution != 100 ? 'disabled' : '' }}>
                                        </td>
                                        @php
                                        $present = $studentAttendance->where('student_enroll_id', $row->id)->where('subject_id', $selected_subject)->where('attendance', 1)->count();

                                        $absent = $studentAttendance->where('student_enroll_id', $row->id)->where('subject_id', $selected_subject)->where('attendance', 2)->count();

                                        $leave = $studentAttendance->where('student_enroll_id', $row->id)->where('subject_id', $selected_subject)->where('attendance', 3)->count();

                                        $total_present = $present + $leave;
                                        $total_attendance = $total_present + $absent;

                                        if(!empty($total_attendance)){
                                            $attendance_mark = ($con_attendances / $total_attendance) * $total_present;
                                        }else{
                                            $attendance_mark = 0;
                                        }
                                        @endphp

                                        @if(isset($con_attendances) && $con_attendances > 0)
                                        <td>
                                            <input type="text" class="form-control attendances autonumber" name="attendances[]" id="attendances" value="{{ round($attendance_mark ?? $attend, 2) }}" style="width: 80px;" data-v-max="{{ $con_attendances }}" data-v-min="0" data-sync="total-{{ $row->id }}" onkeyup="marksCalculator('total', {{ $row->id }})" readonly required {{ $contribution != 100 ? 'disabled' : '' }}>
                                        </td>
                                        @endif
                                        @if(isset($con_assignments) && $con_assignments > 0)
                                        <td>
                                            <input type="text" class="form-control assignments autonumber" name="assignments[]" id="assignments" value="{{ $assignment ? round($assignment, 2) : '' }}" style="width: 80px;" data-v-max="{{ $con_assignments }}" data-v-min="0" data-sync="total-{{ $row->id }}" onkeyup="marksCalculator('total', {{ $row->id }})" required {{ $contribution != 100 ? 'disabled' : '' }}>
                                        </td>
                                        @endif
                                        @if(isset($con_activities) && $con_activities > 0)
                                        <td>
                                            <input type="text" class="form-control activities autonumber" name="activities[]" id="activities" value="{{ $activity ? round($activity, 2) : '' }}" style="width: 80px;" data-v-max="{{ $con_activities }}" data-v-min="0" data-sync="total-{{ $row->id }}" onkeyup="marksCalculator('total', {{ $row->id }})" required {{ $contribution != 100 ? 'disabled' : '' }}>
                                        </td>
                                        @endif

                                        <td>
                                            @php
                                            $total_marks = round($assignment ?? '0', 2) + round($activity ?? '0', 2) + round($exam_marks ?? $mark, 2) + round($attendance_mark ?? $attend, 2);
                                            @endphp
                                            <input type="text" class="form-control total_marks autonumber" name="total_marks[]" id="total_marks" value="{{ round($total_marks, 2) }}" style="width: 80px;" data-v-max="100" data-v-min="0" readonly data-sync="total-{{ $row->id }}" onkeyup="marksCalculator('total', {{ $row->id }})" readonly required>
                                        </td>
                                        @php
                                            $markingRecord = $row->subjectMarks->firstWhere('subject_id', $selected_subject);
                                            $workflowInfo = ($markingRecord && !empty($workflowMeta)) ? ($workflowMeta[$markingRecord->id] ?? null) : null;
                                            $overallMeta = $workflowInfo['overall'] ?? null;
                                            $overallBadge = $overallMeta['badge'] ?? 'secondary';
                                            $overallLabel = $overallMeta['label'] ?? ucwords(str_replace('_', ' ', $overallMeta['state'] ?? 'draft'));
                                            $overallChangedDisplay = $overallMeta['changed_display'] ?? null;
                                            $examWorkflowMeta = $workflowInfo['exam_types'] ?? [];
                                        @endphp
                                        <td>
                                            @if($markingRecord && $overallMeta)
                                                <div class="mb-2">
                                                    <span class="badge badge-{{ $overallBadge }} text-uppercase">{{ $overallLabel }}</span>
                                                    <button type="button" class="btn btn-icon btn-light btn-sm ml-1" onclick="showHistory({{ $markingRecord->id }})" title="View History">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                    @if($overallChangedDisplay)
                                                        <small class="d-block text-muted">{{ $overallChangedDisplay }}</small>
                                                    @endif
                                                </div>
                                                @php
                                                    $overallTransitions = $workflowInfo['overall_transitions'] ?? [];
                                                @endphp
                                                @if(!empty($overallTransitions))
                                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                                        @foreach($overallTransitions as $transition)
                                                            <button type="button" class="btn btn-sm btn-outline-primary mb-1" 
                                                                onclick="openTransitionModal({{ $markingRecord->id }}, null, '{{ $transition }}', '{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}', 'Overall')">
                                                                <i class="fas fa-arrow-right"></i> {{ ucwords(str_replace('_', ' ', $transition)) }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @else
                                                <span class="badge badge-secondary text-uppercase">{{ __('field_not_available') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($row->exams->where('subject_id', $selected_subject)->sortByDesc('contribution') as $exam)
                                                @if($exam->contribution > 0)
                                                @php
                                                    $examStateMeta = $examWorkflowMeta[$exam->exam_type_id] ?? null;
                                                    $examBadge = $examStateMeta['badge'] ?? 'secondary';
                                                    $examStateKey = $examStateMeta['state'] ?? 'draft';
                                                    $examLabel = $examStateMeta['label'] ?? ucwords(str_replace('_', ' ', $examStateKey));
                                                    $examChangedDisplay = $examStateMeta['changed_display'] ?? null;
                                                    $examTransitions = $examStateMeta['transitions'] ?? [];
                                                @endphp
                                                <div class="mb-3 p-2 border rounded">
                                                    <div class="mb-2">
                                                        <span class="badge badge-dark">
                                                            {{ $exam->type->title ?? '' }} - {{ round($exam->achieve_marks ?? '0', 2) }} / {{ round($exam->type->marks ?? '', 2) }} ({{ round($exam->contribution, 2) }}%)
                                                        </span>
                                                        
                                                        {{-- Lock Status & Unlock Button --}}
                                                        @if($exam->marks_locked)
                                                            <span class="badge badge-danger ml-1" title="Marks Locked"><i class="fas fa-lock"></i></span>
                                                            @can('subject-marking-unlock')
                                                                @php
                                                                    $isPublishedAndVisible = $markingRecord && 
                                                                        $markingRecord->workflow_state === 'published' && 
                                                                        $markingRecord->is_published_override !== false;
                                                                @endphp

                                                                @if($isPublishedAndVisible)
                                                                    <span class="d-inline-block" tabindex="0" data-toggle="tooltip" title="Unpublish result to unlock">
                                                                        <button type="button" class="btn btn-icon btn-outline-secondary btn-sm ml-1" style="cursor: not-allowed;" disabled>
                                                                            <i class="fas fa-unlock"></i>
                                                                        </button>
                                                                    </span>
                                                                @else
                                                                    <button type="button" class="btn btn-icon btn-outline-warning btn-sm unlock-exam-marks ml-1" 
                                                                        data-exam-id="{{ $exam->id }}"
                                                                        title="Unlock Marks">
                                                                        <i class="fas fa-unlock"></i>
                                                                    </button>
                                                                @endif
                                                            @endcan
                                                        @endif

                                                        @if($markingRecord && $examStateMeta)
                                                            <span class="badge badge-{{ $examBadge }} ml-1 text-uppercase">{{ $examLabel }}</span>
                                                            @if($markingRecord->is_published_override === false)
                                                                <span class="badge badge-warning ml-1" title="Result is hidden from student"><i class="fas fa-eye-slash"></i></span>
                                                            @endif
                                                            @if($examChangedDisplay)
                                                                <small class="d-block text-muted">{{ $examChangedDisplay }}</small>
                                                            @endif
                                                        @endif
                                                    </div>
                                                    @if($markingRecord && !empty($examTransitions))
                                                        <div class="btn-group-vertical btn-group-sm w-100" role="group">
                                                            @foreach($examTransitions as $transition)
                                                                <button type="button" class="btn btn-sm btn-outline-primary mb-1" 
                                                                    onclick="openTransitionModal({{ $markingRecord->id }}, {{ $exam->exam_type_id }}, '{{ $transition }}', '{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}', '{{ $exam->type->title ?? '' }}')">
                                                                    <i class="fas fa-arrow-right"></i> {{ ucwords(str_replace('_', ' ', $transition)) }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                                @endif
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($markingRecord)
                                                @php
                                                    $publishBadge = $markingRecord->getPublishStatusBadge();
                                                @endphp
                                                
                                                <div class="mb-2">
                                                    <span class="badge {{ $publishBadge['class'] }}">
                                                        <i class="fas {{ $publishBadge['icon'] }}"></i>
                                                        {{ $publishBadge['text'] }}
                                                    </span>
                                                </div>

                                                @if($markingRecord->workflow_state === 'published')
                                                    @if($markingRecord->is_published_override === false)
                                                        {{-- Student is unpublished, show republish button --}}
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="openRepublishModal({{ $markingRecord->id }}, '{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}')">
                                                            <i class="fas fa-unlock"></i> Republish
                                                        </button>
                                                        @if($markingRecord->unpublish_reason)
                                                            <small class="d-block text-muted mt-1" title="{{ $markingRecord->unpublish_reason }}">
                                                                Reason: {{ Str::limit($markingRecord->unpublish_reason, 30) }}
                                                            </small>
                                                        @endif
                                                    @else
                                                        {{-- Student is published or following workflow, show unpublish button --}}
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="openUnpublishModal({{ $markingRecord->id }}, '{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}')">
                                                            <i class="fas fa-lock"></i> Unpublish
                                                        </button>
                                                    @endif
                                                @else
                                                    <small class="text-muted">
                                                        Section not published
                                                    </small>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>

                                @foreach( $subjects as $subject_code )
                                @if($subject_code->id == $selected_subject)
                                @php
                                    $cur_subject = $subject_code->code;
                                @endphp
                                @endif
                                @endforeach

                                <caption>{{ $cur_subject ?? '' }} - {{ $row->session->title ?? '' }}</caption>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>

                    @isset($contribution)
                    @if(count($rows) > 0 && $contribution == 100)
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success update" ><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    @endif
                    @endisset

                    @if(count($rows) < 1)
                    <div class="card-block">
                        <h5>{{ __('no_result_found') }}</h5>
                    </div>
                    @endif
                    @endif
                    </form>

                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Workflow Transition Modal -->
<div class="modal fade" id="transitionModal" tabindex="-1" role="dialog" aria-labelledby="transitionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="transitionForm" method="post" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="transitionModalLabel">Transition Workflow</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Student:</strong> <span id="modal-student-name"></span><br>
                        <strong>Exam Type:</strong> <span id="modal-exam-type"></span><br>
                        <strong>Transition To:</strong> <span id="modal-transition-state" class="text-uppercase"></span>
                    </div>
                    
                    <input type="hidden" name="state" id="modal-state">
                    <input type="hidden" name="exam_type_id" id="modal-exam-type-id">
                    <input type="hidden" name="publish_date" id="modal-publish-date">
                    <input type="hidden" name="publish_time" id="modal-publish-time">
                    
                    <div class="form-group">
                        <label for="modal-notes">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" id="modal-notes" rows="3" placeholder="Add any notes or comments about this transition..."></textarea>
                        <small class="form-text text-muted">Maximum 500 characters</small>
                    </div>
                    
                    <div id="publish-date-time-section" style="display: none;">
                        <hr>
                        <h6>Publication Schedule</h6>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="modal-publish-date-input">Publish Date</label>
                                <input type="date" class="form-control" id="modal-publish-date-input">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="modal-publish-time-input">Publish Time</label>
                                <input type="time" class="form-control" id="modal-publish-time-input">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Confirm Transition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page_js')
    <script type="text/javascript">
        "use strict";
        function marksCalculator(type, id) {

          var selector = "[data-sync='"+type+"-"+id+"']";
          var exam_marks = $(".exam_marks" + selector).val();
          var attendances = $(".attendances" + selector).val();
          var assignments = $(".assignments" + selector).val();
          var activities = $(".activities" + selector).val();
          var total_marks = $(".total_marks" + selector).val();

          if (isNaN(attendances)) attendances = 0;
          if (isNaN(assignments)) assignments = 0;
          if (isNaN(activities)) activities = 0;

          var total_marks = parseFloat(exam_marks) + parseFloat(attendances) + parseFloat(assignments) + parseFloat(activities);

                    $(".attendances" + selector).val(attendances);
                    $(".assignments" + selector).val(assignments);
                    $(".activities" + selector).val(activities);
                    $(".total_marks" + selector).val(total_marks);

        }

        // Sync publish date and time from display fields to hidden fields and bulk transition forms
        function syncPublishDateTime() {
            var publishDate = $('#publish_date').val();
            var publishTime = $('#publish_time').val();
            
            // Update hidden fields in main form for Update button
            $('#publish_date_hidden').val(publishDate);
            $('#publish_time_hidden').val(publishTime);
            
            // Update all bulk transition form hidden fields
            $('.bulk-transition-form .bulk-publish-date-field').val(publishDate);
            $('.bulk-transition-form .bulk-publish-time-field').val(publishTime);
        }

        // Open transition modal
        function openTransitionModal(markingId, examTypeId, state, studentName, examType) {
            // Set student and exam info
            $('#modal-student-name').text(studentName);
            $('#modal-exam-type').text(examType);
            $('#modal-transition-state').text(state.replace(/_/g, ' ').toUpperCase());
            
            // Set hidden form values
            $('#modal-state').val(state);
            $('#modal-exam-type-id').val(examTypeId || '');
            
            // Set form action
            var formAction = '{{ url("admin/exam/subject-marking") }}/' + markingId + '/transition';
            $('#transitionForm').attr('action', formAction);
            
            // Get publish date/time from main form
            var publishDate = $('#publish_date').val();
            var publishTime = $('#publish_time').val();
            
            // Set modal publish date/time inputs
            $('#modal-publish-date-input').val(publishDate);
            $('#modal-publish-time-input').val(publishTime);
            
            // Update hidden fields
            $('#modal-publish-date').val(publishDate);
            $('#modal-publish-time').val(publishTime);
            
            // Show/hide publish date/time section for publish transition
            if (state.toLowerCase() === 'publish') {
                $('#publish-date-time-section').show();
            } else {
                $('#publish-date-time-section').hide();
            }
            
            // Clear notes
            $('#modal-notes').val('');
            
            // Show modal
            $('#transitionModal').modal('show');
        }
        
        // Update hidden fields when modal publish date/time changes
        $('#modal-publish-date-input, #modal-publish-time-input').on('change', function() {
            $('#modal-publish-date').val($('#modal-publish-date-input').val());
            $('#modal-publish-time').val($('#modal-publish-time-input').val());
        });

        // History logic
        function showHistory(markingId) {
            $('#historyModal').modal('show');
            $('#history-content').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading history...</div>');
            
            $.ajax({
                url: '{{ url("admin/exam/subject-marking") }}/' + markingId + '/history',
                type: 'GET',
                success: function(data) {
                    var html = '<div class="timeline">';
                    if(data.length === 0) {
                        html += '<div class="alert alert-info">No history found for this student.</div>';
                    } else {
                        $.each(data, function(index, log) {
                            html += '<div class="mb-3 border-bottom pb-2">';
                            html += '<div class="d-flex justify-content-between align-items-center">';
                            html += '<strong class="text-primary">' + log.action + '</strong>';
                            html += '<small class="text-muted"><i class="far fa-clock"></i> ' + log.date + '</small>';
                            html += '</div>';
                            html += '<div class="small text-dark mt-1"><i class="far fa-user"></i> ' + log.user + '</div>';
                            if(log.note) {
                                html += '<div class="mt-1 p-2 bg-light rounded text-muted small"><em>Note: ' + log.note + '</em></div>';
                            }
                            html += '</div>';
                        });
                    }
                    html += '</div>';
                    $('#history-content').html(html);
                },
                error: function() {
                    $('#history-content').html('<div class="alert alert-danger">Failed to load history. Please try again.</div>');
                }
            });
        }

        // Sync on page load
        $(document).ready(function() {
            syncPublishDateTime();

            // Unlock exam marks from subject marking page
            $(".unlock-exam-marks").on('click', function(){
                var btn = $(this);
                var examId = btn.data('exam-id');
                
                if(confirm('Are you sure you want to unlock this mark record?')){
                    // Show loading state
                    var originalHtml = btn.html();
                    btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

                    $.ajax({
                        url: '{{ route("admin.exam-marking.unlock") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            exam_id: examId
                        },
                        success: function(response){
                            if(response.status == 'success'){
                                // Success - reload to reflect changes
                                location.reload();
                            } else {
                                alert(response.message);
                                btn.html(originalHtml).prop('disabled', false);
                            }
                        },
                        error: function(xhr){
                            alert('Error: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error'));
                            btn.html(originalHtml).prop('disabled', false);
                        }
                    });
                }
            });

            // Auto-save logic
            $('.exam_marks, .attendances, .assignments, .activities').on('change', function() {
                var input = $(this);
                var rowId = input.data('sync').split('-')[1];
                var studentId = input.closest('tr').find('input[name="students[]"]').val();
                var subjectId = input.closest('tr').find('input[name="subjects[]"]').val();
                
                // Get all values for this row to ensure consistency
                var selector = "[data-sync='total-"+rowId+"']";
                var exam_marks = $(".exam_marks" + selector).val();
                var attendances = $(".attendances" + selector).val();
                var assignments = $(".assignments" + selector).val();
                var activities = $(".activities" + selector).val();
                var publishDate = $('#publish_date').val();
                var publishTime = $('#publish_time').val();

                // Show saving indicator
                var originalBorder = input.css('border');
                input.css('border', '2px solid #ffc107'); // Yellow for saving
                
                // Optional: Add a small saving text
                var statusSpan = input.siblings('.save-status');
                if(statusSpan.length === 0) {
                    input.after('<span class="save-status small text-warning d-block">Saving...</span>');
                    statusSpan = input.siblings('.save-status');
                } else {
                    statusSpan.removeClass('text-success text-danger').addClass('text-warning').text('Saving...');
                }

                $.ajax({
                    url: '{{ route("admin.subject-marking.autosave") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        student_id: studentId,
                        subject_id: subjectId,
                        exam_marks: exam_marks,
                        attendances: attendances,
                        assignments: assignments,
                        activities: activities,
                        publish_date: publishDate,
                        publish_time: publishTime
                    },
                    success: function(response) {
                        if(response.status === 'success') {
                            input.css('border', '2px solid #28a745'); // Green for success
                            statusSpan.removeClass('text-warning').addClass('text-success').text('Saved');
                            
                            setTimeout(function() {
                                input.css('border', originalBorder);
                                statusSpan.fadeOut(500, function() { $(this).remove(); });
                            }, 2000);
                            
                            // Update total if returned
                            if(response.total_marks) {
                                $(".total_marks" + selector).val(response.total_marks);
                            }
                        } else {
                            input.css('border', '2px solid #dc3545'); // Red for error
                            statusSpan.removeClass('text-warning').addClass('text-danger').text('Error');
                            alert('Error saving: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        input.css('border', '2px solid #dc3545'); // Red for error
                        statusSpan.removeClass('text-warning').addClass('text-danger').text('Failed');
                        console.error(xhr.responseText);
                        // Try to parse error message
                        var msg = 'Unknown error';
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert('Failed to save marks. Please check your connection. Error: ' + msg);
                    }
                });
            });
        });

        // Sync whenever publish date/time changes
        $('#publish_date, #publish_time').on('change', function() {
            syncPublishDateTime();
        });

        // Open unpublish modal
        function openUnpublishModal(markingId, studentName) {
            $('#unpublish-student-name').text(studentName);
            $('#unpublish-reason').val('');
            
            var formAction = '{{ url("admin/exam/subject-marking") }}/' + markingId + '/unpublish';
            $('#unpublishForm').attr('action', formAction);
            
            $('#unpublishModal').modal('show');
        }

        // Open republish modal
        function openRepublishModal(markingId, studentName) {
            $('#republish-student-name').text(studentName);
            $('#republish-reason').val('');
            
            var formAction = '{{ url("admin/exam/subject-marking") }}/' + markingId + '/republish';
            $('#republishForm').attr('action', formAction);
            
            $('#republishModal').modal('show');
        }
    </script>

    <!-- Unpublish Modal -->
    <div class="modal fade" id="unpublishModal" tabindex="-1" role="dialog" aria-labelledby="unpublishModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="unpublishForm" method="POST" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="unpublishModalLabel">
                            <i class="fas fa-lock text-warning"></i> Unpublish Student Result
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This will hide the result for <strong id="unpublish-student-name"></strong> from the student portal, even though the section is published.
                        </div>

                        <div class="form-group">
                            <label for="unpublish-reason">Reason for Unpublishing <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="unpublish-reason" name="reason" rows="4" 
                                placeholder="E.g., Grade appeal pending, Exam misconduct investigation, Missing coursework..." 
                                required minlength="10" maxlength="500"></textarea>
                            <small class="form-text text-muted">
                                Please provide a clear reason (minimum 10 characters). This will be logged for audit purposes.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-lock"></i> Unpublish Result
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Republish Modal -->
    <div class="modal fade" id="republishModal" tabindex="-1" role="dialog" aria-labelledby="republishModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="republishForm" method="POST" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="republishModalLabel">
                            <i class="fas fa-unlock text-success"></i> Republish Student Result
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="fas fa-info-circle"></i>
                            This will make the result for <strong id="republish-student-name"></strong> visible again in the student portal.
                        </div>

                        <div class="form-group">
                            <label for="republish-reason">Reason for Republishing (Optional)</label>
                            <textarea class="form-control" id="republish-reason" name="reason" rows="3" 
                                placeholder="E.g., Appeal resolved, Correction completed..." 
                                maxlength="500"></textarea>
                            <small class="form-text text-muted">
                                Optional: Provide context for republishing this result.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-unlock"></i> Republish Result
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">
                        <i class="fas fa-history"></i> Workflow History
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="history-content">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection
