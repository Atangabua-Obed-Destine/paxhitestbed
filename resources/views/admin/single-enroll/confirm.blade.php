    <!-- Enhanced Enrollment Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="ConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark" id="confirmModalHeader">
                    <h5 class="modal-title" id="ConfirmModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> <span id="confirmModalTitle">{{ __('Enrollment Readiness Check') }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    <div class="alert alert-info">
                        <strong><i class="fas fa-info-circle"></i> {{ __('Important:') }}</strong> 
                        {{ __('Please review the student\'s current enrollment status before proceeding to the next semester.') }}
                    </div>

                    <h6 class="text-primary mb-3"><i class="fas fa-graduation-cap"></i> {{ __('Current Enrollment Summary') }}</h6>
                    
                    @if(isset($row) && isset($row->currentEnroll))
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('field_session') }}:</strong> {{ $row->currentEnroll->session->title ?? 'N/A' }}</p>
                            <p class="mb-1"><strong>{{ __('field_semester') }}:</strong> {{ $row->currentEnroll->semester->title ?? 'N/A' }}</p>
                            <p class="mb-1"><strong>{{ __('field_section') }}:</strong> {{ $row->currentEnroll->section->title ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            @php
                                $current_sem_credits = 0;
                                $current_sem_cgpa = 0;
                                $subjects_with_grades = 0;
                                $total_subjects = $row->currentEnroll->subjects->count();
                            @endphp
                            
                            @foreach($row->currentEnroll->subjects as $subject)
                                @php
                                    $current_sem_credits += $subject->credit_hour;
                                    $has_grade = false;
                                @endphp
                                
                                @if(isset($row->currentEnroll->subjectMarks))
                                    @foreach($row->currentEnroll->subjectMarks as $mark)
                                        @if($mark->subject_id == $subject->id)
                                            @php
                                                $marks_per = round($mark->total_marks);
                                                $has_grade = true;
                                                $subjects_with_grades++;
                                            @endphp
                                            @foreach($grades as $grade)
                                                @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
                                                    @php
                                                        $current_sem_cgpa += ($grade->point * $subject->credit_hour);
                                                    @endphp
                                                    @break
                                                @endif
                                            @endforeach
                                        @endif
                                    @endforeach
                                @endif
                            @endforeach
                            
                            <p class="mb-1"><strong>{{ __('Total Subjects') }}:</strong> {{ $total_subjects }}</p>
                            <p class="mb-1"><strong>{{ __('Subjects with Grades') }}:</strong> {{ $subjects_with_grades }}</p>
                            <p class="mb-1"><strong>{{ __('Total Credits') }}:</strong> {{ number_format($current_sem_credits, 2) }}</p>
                            <p class="mb-1"><strong>{{ __('Semester GPA') }}:</strong> 
                                {{ $current_sem_credits > 0 ? number_format($current_sem_cgpa / $current_sem_credits, 2) : 'N/A' }}
                            </p>
                        </div>
                    </div>

                    <hr>

                    <h6 class="text-primary mb-3"><i class="fas fa-clipboard-check"></i> {{ __('Enrollment Validation Status') }}</h6>
                    
                    <div class="checklist">
                        @if($subjects_with_grades == $total_subjects && $total_subjects > 0)
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>{{ __('All subjects have been graded') }}</strong>
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle"></i> <strong>{{ __('Warning:') }}</strong> 
                                {{ ($total_subjects - $subjects_with_grades) }} {{ __('subject(s) do not have grades yet') }}
                            </div>
                        @endif

                        @php
                            $failed_subjects = 0;
                            foreach($row->currentEnroll->subjects as $subject) {
                                if(isset($row->currentEnroll->subjectMarks)) {
                                    foreach($row->currentEnroll->subjectMarks as $mark) {
                                        if($mark->subject_id == $subject->id) {
                                            $marks_per = round($mark->total_marks);
                                            foreach($grades as $grade) {
                                                if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                                    if($grade->point < 1.0) {
                                                        $failed_subjects++;
                                                    }
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        @endphp

                        @if($failed_subjects > 0)
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle"></i> <strong>{{ __('Warning:') }}</strong> 
                                {{ $failed_subjects }} {{ __('subject(s) failed (Grade F or equivalent)') }}
                            </div>
                        @else
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>{{ __('No failed subjects') }}</strong>
                            </div>
                        @endif
                    </div>

                    <hr>

                    <h6 class="text-primary mb-3"><i class="fas fa-list-check"></i> {{ __('Conditions for Next Enrollment') }}</h6>
                    <ul class="mb-0">
                        <li>{{ __('Ensure all current semester subjects have been graded') }}</li>
                        <li>{{ __('Review student\'s academic performance and GPA') }}</li>
                        <li>{{ __('Check if student meets the requirements for progression') }}</li>
                        <li>{{ __('Verify that any failed subjects are addressed (retake or remedial)') }}</li>
                        <li>{{ __('Confirm student has paid required fees for next semester') }}</li>
                        <li>{{ __('Ensure student is not on academic probation or suspension') }}</li>
                    </ul>
                    @else
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> {{ __('Unable to load current enrollment information') }}
                    </div>
                    @endif

                    <div class="alert alert-danger mt-3 mb-0">
                        <strong><i class="fas fa-exclamation-triangle"></i> {{ __('Warning:') }}</strong> 
                        {{ __('Proceeding will enroll the student into the selected semester. This action should only be taken after verifying the student is ready.') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> {{ __('Confirm Enrollment') }}
                    </button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->