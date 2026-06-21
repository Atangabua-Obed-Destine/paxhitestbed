<!-- Enrollment Status Toggle Modal -->
<div class="modal fade" id="enrollStatusModal-{{ $enrollment->id }}" tabindex="-1" role="dialog" aria-labelledby="enrollStatusModalLabel-{{ $enrollment->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header {{ $enrollment->status == 1 ? 'bg-danger' : 'bg-success' }}">
                <h5 class="modal-title text-white" id="enrollStatusModalLabel-{{ $enrollment->id }}">
                    @if($enrollment->status == 1)
                    <i class="fas fa-user-slash me-2"></i>{{ __('btn_deactivate') }} {{ __('field_enrollment') }}
                    @else
                    <i class="fas fa-user-check me-2"></i>{{ __('btn_activate') }} {{ __('field_enrollment') }}
                    @endif
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Student Info -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('field_student') }} {{ __('field_information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_name') }}:</strong></p>
                                <p class="text-muted">{{ $studentData->first_name }} {{ $studentData->last_name }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_student_id') }}:</strong></p>
                                <p class="text-muted">{{ $studentData->student_id }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Info -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>{{ __('field_enrollment') }} {{ __('field_information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_matricule') }}:</strong></p>
                                <p class="text-primary fw-bold">{{ $enrollment->matricule }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('status_current') }} {{ __('field_status') }}:</strong></p>
                                <p>
                                    @if($enrollment->status == 1)
                                    <span class="badge bg-success">{{ __('status_active') }}</span>
                                    @else
                                    <span class="badge bg-danger">{{ __('status_inactive') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_program') }}:</strong></p>
                                <p class="text-muted">{{ $enrollment->program->title ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_session') }}:</strong></p>
                                <p class="text-muted">{{ $enrollment->session->title ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_semester') }}:</strong></p>
                                <p class="text-muted">{{ $enrollment->semester->title ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>{{ __('field_section') }}:</strong></p>
                                <p class="text-muted">{{ $enrollment->section->title ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning Message -->
                <div class="alert {{ $enrollment->status == 1 ? 'alert-danger' : 'alert-success' }}" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                        <div>
                            @if($enrollment->status == 1)
                            <strong>{{ __('field_warning') }}!</strong><br>
                            {{ __('msg_deactivate_enrollment_warning') ?? 'Deactivating this enrollment will prevent the student from accessing courses, registering for subjects, and appearing in active student lists for this program.' }}
                            @else
                            <strong>{{ __('field_note') }}:</strong><br>
                            {{ __('msg_activate_enrollment_info') ?? 'Activating this enrollment will allow the student to access courses, register for subjects, and appear in active student lists for this program.' }}
                            @endif
                        </div>
                    </div>
                </div>

                <p class="text-center mb-0">
                    @if($enrollment->status == 1)
                    {{ __('msg_confirm_deactivate') ?? 'Are you sure you want to deactivate this enrollment?' }}
                    @else
                    {{ __('msg_confirm_activate') ?? 'Are you sure you want to activate this enrollment?' }}
                    @endif
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>{{ __('btn_close') }}
                </button>
                <form action="{{ route('admin.student.toggle-enroll-status', $enrollment->id) }}" method="POST" style="display: inline;">
                    @csrf
                    @if($enrollment->status == 1)
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-user-slash me-1"></i>{{ __('btn_deactivate') }}
                    </button>
                    @else
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-check me-1"></i>{{ __('btn_activate') }}
                    </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
