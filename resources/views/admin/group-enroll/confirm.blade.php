    <!-- Enhanced Group Enrollment Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="ConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="ConfirmModalLabel">
                        <i class="fas fa-users"></i> {{ __('Group Enrollment Readiness Check') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong><i class="fas fa-info-circle"></i> {{ __('Important:') }}</strong> 
                        {{ __('You are about to enroll multiple students. Please review the group\'s current status before proceeding.') }}
                    </div>

                    @isset($rows)
                    @php
                        $total_students = 0;
                        $students_with_all_grades = 0;
                        $students_with_failed_subjects = 0;
                        $students_without_grades = 0;
                        $total_subjects_count = 0;
                        $total_graded_subjects = 0;
                        $average_group_gpa = 0;
                        $students_data = [];
                    @endphp

                    @foreach($rows as $row)
                        @php
                            $total_students++;
                            $student_total_credits = 0;
                            $student_total_cgpa = 0;
                            $student_subjects = $row->currentEnroll->subjects ?? collect();
                            $student_has_all_grades = true;
                            $student_has_failed = false;
                            $student_graded_count = 0;
                            $student_subject_count = $student_subjects->count();
                            $total_subjects_count += $student_subject_count;
                        @endphp

                        @foreach($student_subjects as $subject)
                            @php
                                $student_total_credits += $subject->credit_hour;
                                $subject_has_grade = false;
                            @endphp

                            @if(isset($row->currentEnroll->subjectMarks))
                                @foreach($row->currentEnroll->subjectMarks as $mark)
                                    @if($mark->subject_id == $subject->id)
                                        @php
                                            $marks_per = round($mark->total_marks);
                                            $subject_has_grade = true;
                                            $student_graded_count++;
                                            $total_graded_subjects++;
                                        @endphp

                                        @foreach($grades as $grade)
                                            @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                                                @php
                                                    $student_total_cgpa += ($grade->point * $subject->credit_hour);
                                                    if($grade->point < 1.0) {
                                                        $student_has_failed = true;
                                                    }
                                                @endphp
                                                @break
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach
                            @endif

                            @if(!$subject_has_grade)
                                @php $student_has_all_grades = false; @endphp
                            @endif
                        @endforeach

                        @php
                            if($student_has_all_grades && $student_subject_count > 0) {
                                $students_with_all_grades++;
                            }
                            if(!$student_has_all_grades && $student_subject_count > 0) {
                                $students_without_grades++;
                            }
                            if($student_has_failed) {
                                $students_with_failed_subjects++;
                            }
                            if($student_total_credits > 0) {
                                $average_group_gpa += ($student_total_cgpa / $student_total_credits);
                            }

                            $students_data[] = [
                                'id' => $row->student_id,
                                'name' => $row->first_name . ' ' . $row->last_name,
                                'has_all_grades' => $student_has_all_grades,
                                'has_failed' => $student_has_failed,
                                'gpa' => $student_total_credits > 0 ? number_format($student_total_cgpa / $student_total_credits, 2) : 'N/A',
                                'graded' => $student_graded_count,
                                'total' => $student_subject_count
                            ];
                        @endphp
                    @endforeach

                    @php
                        $average_group_gpa = $total_students > 0 ? ($average_group_gpa / $total_students) : 0;
                    @endphp

                    <h6 class="text-primary mb-3"><i class="fas fa-chart-bar"></i> {{ __('Group Statistics') }}</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $total_students }}</h3>
                                    <small>{{ __('Total Students') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $students_with_all_grades }}</h3>
                                    <small>{{ __('Fully Graded') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $students_without_grades }}</h3>
                                    <small>{{ __('Incomplete Grades') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">{{ $students_with_failed_subjects }}</h3>
                                    <small>{{ __('With Failed Subjects') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('Total Subjects (All Students)') }}:</strong> {{ $total_subjects_count }}</p>
                            <p class="mb-1"><strong>{{ __('Graded Subjects') }}:</strong> {{ $total_graded_subjects }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('Grading Completion Rate') }}:</strong> 
                                {{ $total_subjects_count > 0 ? number_format(($total_graded_subjects / $total_subjects_count) * 100, 1) : 0 }}%
                            </p>
                            <p class="mb-1"><strong>{{ __('Average Group GPA') }}:</strong> {{ number_format($average_group_gpa, 2) }}</p>
                        </div>
                    </div>

                    <hr>

                    <h6 class="text-primary mb-3"><i class="fas fa-clipboard-check"></i> {{ __('Group Validation Status') }}</h6>
                    
                    @if($students_with_all_grades == $total_students && $total_students > 0)
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <strong>{{ __('Excellent!') }}</strong> 
                            {{ __('All students have complete grades for all subjects') }}
                        </div>
                    @elseif($students_without_grades > 0)
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle"></i> <strong>{{ __('Warning:') }}</strong> 
                            {{ $students_without_grades }} {{ __('student(s) have incomplete grades') }}
                        </div>
                    @endif

                    @if($students_with_failed_subjects > 0)
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> <strong>{{ __('Critical:') }}</strong> 
                            {{ $students_with_failed_subjects }} {{ __('student(s) have failed subject(s)') }}
                        </div>
                    @else
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <strong>{{ __('No students with failed subjects') }}</strong>
                        </div>
                    @endif

                    <hr>

                    <h6 class="text-primary mb-3"><i class="fas fa-users-cog"></i> {{ __('Individual Student Status') }}</h6>
                    
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>{{ __('Student ID') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Grades') }}</th>
                                    <th>{{ __('GPA') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($students_data as $student)
                                <tr>
                                    <td>{{ $student['id'] }}</td>
                                    <td>{{ $student['name'] }}</td>
                                    <td>{{ $student['graded'] }}/{{ $student['total'] }}</td>
                                    <td>{{ $student['gpa'] }}</td>
                                    <td>
                                        @if($student['has_failed'])
                                            <span class="badge bg-danger">{{ __('Has Failed') }}</span>
                                        @elseif(!$student['has_all_grades'])
                                            <span class="badge bg-warning text-dark">{{ __('Incomplete') }}</span>
                                        @else
                                            <span class="badge bg-success">{{ __('Complete') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    <h6 class="text-primary mb-3"><i class="fas fa-list-check"></i> {{ __('Group Enrollment Checklist') }}</h6>
                    <ul class="mb-0">
                        <li>{{ __('Verify all students have completed grades for current semester') }}</li>
                        <li>{{ __('Review overall group performance and average GPA') }}</li>
                        <li>{{ __('Ensure students with failed subjects are addressed (retake/remedial)') }}</li>
                        <li>{{ __('Check that students meet progression requirements') }}</li>
                        <li>{{ __('Confirm fee payments for all students') }}</li>
                        <li>{{ __('Verify no students are on academic probation or suspension') }}</li>
                        <li>{{ __('Ensure proper course offerings for next semester') }}</li>
                    </ul>
                    @else
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> {{ __('No students found for enrollment') }}
                    </div>
                    @endisset

                    <div class="alert alert-danger mt-3 mb-0">
                        <strong><i class="fas fa-exclamation-triangle"></i> {{ __('Warning:') }}</strong> 
                        {{ __('Proceeding will enroll ALL SELECTED students into the next semester. Review the statistics above carefully before confirming.') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel & Review') }}
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-users"></i> {{ __('Confirm Group Enrollment') }}
                    </button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->