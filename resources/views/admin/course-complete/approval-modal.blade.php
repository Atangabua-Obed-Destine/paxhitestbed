<!-- Enhanced Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" role="dialog" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="approvalModalLabel">
                    <i class="fas fa-user-graduate"></i> Confirm Graduation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Important:</strong> You are about to graduate <strong id="studentCount">0</strong> student(s). This action will:
                    <ul class="mt-2 mb-0">
                        <li>Mark selected students as "Alumni" (status = 2)</li>
                        <li>Deactivate their current enrollment</li>
                        <li>Remove them from active student lists</li>
                        <li>This action cannot be easily reversed</li>
                    </ul>
                </div>
                
                <h6 class="mb-3"><i class="fas fa-list"></i> Students to be Graduated:</h6>
                <div id="graduationSummaryList" style="max-height: 400px; overflow-y: auto;">
                    <!-- Student list will be populated by JavaScript -->
                </div>
                
                <div class="mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmCheckbox" required>
                        <label class="form-check-label" for="confirmCheckbox">
                            <strong>I confirm that I have reviewed the list above and verified that all students meet the graduation requirements.</strong>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                </button>
                <button type="button" class="btn btn-success" onclick="confirmGraduation()" id="confirmButton" disabled>
                    <i class="fas fa-check"></i> {{ __('btn_confirm') }} Graduation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Enable confirm button only when checkbox is checked
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirmCheckbox');
    const confirmButton = document.getElementById('confirmButton');
    
    if(confirmCheckbox && confirmButton) {
        confirmCheckbox.addEventListener('change', function() {
            confirmButton.disabled = !this.checked;
        });
    }
    
    // Reset checkbox when modal is closed
    $('#approvalModal').on('hidden.bs.modal', function () {
        if(confirmCheckbox) confirmCheckbox.checked = false;
        if(confirmButton) confirmButton.disabled = true;
    });
});
</script>
