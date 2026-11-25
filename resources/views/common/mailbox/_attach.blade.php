<div class="mt-2">
    <label class="form-label mb-1" for="mbx-attachments-{{ $attachId ?? 'r' }}"><i class="fas fa-paperclip"></i> {{ __('Attachments') }} <span class="mbx-help">({{ __('up to :n files, :mb MB each: PDF, Word, Excel, PowerPoint, images', ['n' => \App\Services\Mailbox\Mailbox::MAX_FILES, 'mb' => \App\Services\Mailbox\Mailbox::MAX_KB / 1024]) }})</span></label>
    <input id="mbx-attachments-{{ $attachId ?? 'r' }}" type="file" name="attachments[]" multiple class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv">
    @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @error('attachments.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
