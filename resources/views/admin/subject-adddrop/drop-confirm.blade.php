<!-- Drop Subject Confirmation Modal -->
<div class="modal fade" id="dropConfirmModal" tabindex="-1" role="dialog" aria-labelledby="dropConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dropConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning"></i> {{ __('Confirm Drop Subject') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{{ __('Are you sure you want to drop this subject?') }}</p>
                <div class="alert alert-warning">
                    <strong>{{ __('field_subject') }}:</strong> <span id="drop-subject-label"></span>
                </div>
                <p class="text-muted mb-0">
                    <small>
                        <i class="fas fa-info-circle"></i> 
                        {{ __('This action will remove the subject from the student\'s current enrollment.') }}
                    </small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                </button>
                <button type="button" class="btn btn-danger" id="confirm-drop-button">
                    <i class="fas fa-check"></i> {{ __('Yes, Drop Subject') }}
                </button>
            </div>
        </div>
    </div>
</div>
