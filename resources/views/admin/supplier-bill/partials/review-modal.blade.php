{{--
    The review dialog every posting action goes through.

    Two ways in:

    1. A trigger button (supplier bills, payments, bank reconciliation, month
       close, reversals), with
        data-review-url    where the preview is fetched from (GET), or
        data-review-form   the id of a form whose fields are posted to
                           data-review-url to build the preview
        data-confirm-form  the id of the form that confirming submits
        data-confirm-label the confirm button's text
        data-confirm-check the wording of the tick box (optional)
        data-needs-reason  "1" when a reason must be typed before confirming

    2. Any form marked data-review="<tick-box wording>" (payroll, tax, loans,
       transfers, depreciation, recurring entries, credits, journal posting,
       year end). Submitting it opens this dialog instead; the preview is the
       form's own request replayed and rolled back (ReviewController::dryRun).
       Optional: data-review-label (confirm button), data-review-reason="1".

    Confirming needs three things: the preview reports no problem, the person
    has ticked that they checked it, and (where asked) a reason. Confirming
    adds reviewed=1 to the form; the server refuses a posting without it
    (RequireReview, or the controller), so the review cannot be skipped.

    Included once per page (the layout includes it everywhere).
--}}
@once
@include('admin.supplier-bill.partials.style')
<div class="modal fade" id="reviewModal" tabindex="-1" role="dialog" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">{{ __('Review before confirming') }}</h5>
                <button type="button" class="close btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="review-body"><div class="ap-muted">{{ __('Checking…') }}</div></div>
                <div id="review-reason" class="mt-3" style="display:none">
                    <label for="review-reason-input">{{ __('Reason') }} <span>*</span></label>
                    <textarea id="review-reason-input" class="form-control" maxlength="1000" rows="2"></textarea>
                </div>
                <div class="ap-confirm-check mt-3" id="review-check-box">
                    <input type="checkbox" id="review-check" disabled>
                    <label for="review-check" id="review-check-label">{{ __('I have checked the details above and confirm they are correct.') }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Go back and change') }}</button>
                <button type="button" class="btn btn-success" id="review-confirm" disabled>{{ __('Confirm') }}</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var modal = $('#reviewModal');
    var body = document.getElementById('review-body');
    var confirmBtn = document.getElementById('review-confirm');
    var reasonBox = document.getElementById('review-reason');
    var reasonInput = document.getElementById('review-reason-input');
    var check = document.getElementById('review-check');
    var checkBox = document.getElementById('review-check-box');
    var checkLabel = document.getElementById('review-check-label');
    var defaultCheck = @json(__('I have checked the details above and confirm they are correct.'));
    var dryRunUrl = @json(route('admin.review.dry-run'));
    var current = null; // { needsReason, confirmForm }

    function previewOk() {
        var p = body.querySelector('.ap-preview');
        return !!p && p.getAttribute('data-ok') === '1';
    }

    function refresh() {
        var ok = previewOk();
        var needsReason = current && current.needsReason;
        // Nothing to vouch for while the action would be refused.
        check.disabled = !ok;
        if (!ok) { check.checked = false; }
        checkBox.classList.toggle('is-ready', ok);
        confirmBtn.disabled = !ok || !check.checked || (needsReason && reasonInput.value.trim() === '');
    }

    function fail(xhr) {
        var msg = @json(__('The check could not be run.'));
        if (xhr && xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            msg = Object.values(xhr.responseJSON.errors).map(function (m) { return m[0]; }).join(' ');
        } else if (xhr && xhr.status === 403) {
            msg = @json(__('You are not allowed to do this.'));
        }
        body.innerHTML = '<div class="ap-preview" data-ok="0"><div class="ap-note is-bad">' + $('<div>').text(msg).html() + '</div></div>';
        refresh();
    }

    /**
     * Open the dialog.
     * cfg: { request: () => jqXHR, confirmForm, label, cssClass, check, needsReason }
     */
    function open(cfg) {
        current = cfg;
        body.innerHTML = '<div class="ap-muted"></div>';
        body.firstChild.textContent = @json(__('Checking…'));
        confirmBtn.textContent = cfg.label || @json(__('Confirm'));
        confirmBtn.className = 'btn ' + (cfg.cssClass || 'btn-success');
        checkLabel.textContent = cfg.check || defaultCheck;
        check.checked = false;
        reasonInput.value = '';
        reasonBox.style.display = cfg.needsReason ? '' : 'none';
        refresh();
        modal.modal('show');
        cfg.request().done(function (html) { body.innerHTML = html; refresh(); }).fail(fail);
    }

    window.PaxReview = { open: open };

    // 1. Trigger buttons.
    $(document).on('click', '[data-review-url]', function (e) {
        e.preventDefault();
        var el = this;
        var formId = el.getAttribute('data-review-form');
        if (formId) {
            var form = document.getElementById(formId);
            if (!form.reportValidity()) { return; }
        }
        open({
            request: function () {
                return formId
                    ? $.post(el.getAttribute('data-review-url'), $('#' + formId).serialize())
                    : $.get(el.getAttribute('data-review-url'));
            },
            confirmForm: document.getElementById(el.getAttribute('data-confirm-form')),
            label: el.getAttribute('data-confirm-label'),
            cssClass: el.getAttribute('data-confirm-class'),
            check: el.getAttribute('data-confirm-check'),
            needsReason: el.getAttribute('data-needs-reason') === '1'
        });
    });

    // 2. Any form marked data-review: its own request, replayed and rolled back.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-review]') || form.getAttribute('data-reviewed') === '1') { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        if (form.reportValidity && !form.reportValidity()) { return; }
        var methodInput = form.querySelector('input[name="_method"]');
        var payload = $(form).serialize()
            + '&_review_target=' + encodeURIComponent(form.getAttribute('action'))
            + '&_review_method=' + encodeURIComponent((methodInput && methodInput.value) || form.getAttribute('method') || 'POST');
        // Close any dialog the form lives in, so the review is on top.
        $(form).closest('.modal').modal('hide');
        open({
            request: function () { return $.post(dryRunUrl, payload); },
            confirmForm: form,
            label: form.getAttribute('data-review-label') || @json(__('Confirm and post')),
            cssClass: form.getAttribute('data-review-class'),
            check: form.getAttribute('data-review'),
            needsReason: form.getAttribute('data-review-reason') === '1'
        });
    }, true);

    reasonInput.addEventListener('input', refresh);
    check.addEventListener('change', refresh);

    confirmBtn.addEventListener('click', function () {
        if (confirmBtn.disabled || !current || !check.checked) { return; }
        var form = current.confirmForm;
        if (current.needsReason) {
            var reasonField = form.querySelector('[name="void_reason"], [name="reason"], [name="cancel_reason"]');
            if (!reasonField) {
                reasonField = document.createElement('input');
                reasonField.type = 'hidden';
                reasonField.name = 'void_reason';
                form.appendChild(reasonField);
            }
            reasonField.value = reasonInput.value.trim();
        }
        var flag = form.querySelector('input[name="reviewed"]');
        if (!flag) {
            flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = 'reviewed';
            form.appendChild(flag);
        }
        flag.value = '1';
        form.setAttribute('data-reviewed', '1');
        confirmBtn.disabled = true;
        confirmBtn.textContent = @json(__('Working…'));
        HTMLFormElement.prototype.submit.call(form);
    });
})();
</script>
@endpush
@endonce
