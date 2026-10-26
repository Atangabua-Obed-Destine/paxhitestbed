{{--
  Renaming a category, or taking it out of use.

  Rendered inside the list's loop, so it has $row. Same shape as the record
  card: a quiet header, hairlines, one clear action.

  The status note is there because what it does is not obvious from the word
  "Inactive": the category keeps every figure already recorded against it, and
  only stops being offered on new entries.
--}}

<div id="editModal-{{ $row->id }}" class="modal fade acct-record" tabindex="-1"
     aria-labelledby="editModalLabel-{{ $row->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate method="post"
                  action="{{ route($route.'.update', $row->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <div>
                        <div class="acct-label">{{ $title }}</div>
                        <h5 class="modal-title mt-1" id="editModalLabel-{{ $row->id }}"
                            style="font-size:.95rem;font-weight:700;">
                            {{ __('modal_edit') }}
                        </h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="{{ __('btn_close') }}"></button>
                </div>

                <div class="modal-body" style="padding:1.35rem;">
                    <div class="form-group mb-3">
                        <label for="title-{{ $row->id }}" class="form-label"
                               style="font-size:.76rem;font-weight:600;color:var(--body);">
                            {{ __('field_title') }} <span style="color:var(--bad);">*</span>
                        </label>
                        <input type="text" class="form-control" name="title" id="title-{{ $row->id }}"
                               value="{{ $row->title }}" required>
                        <div class="invalid-feedback">
                            {{ __('required_field') }} {{ __('field_title') }}
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="status-{{ $row->id }}" class="form-label"
                               style="font-size:.76rem;font-weight:600;color:var(--body);">
                            {{ __('select_status') }}
                        </label>
                        <select class="form-control" name="status" id="status-{{ $row->id }}">
                            <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                            <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                        </select>
                        <small class="form-text" style="font-size:.74rem;color:var(--muted);">
                            {{ __('acc_cat_status_help') }}
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="acct-btn" data-bs-dismiss="modal">
                        {{ __('btn_close') }}
                    </button>
                    <button type="submit" class="acct-btn acct-btn-main">
                        <i class="fas fa-check"></i>{{ __('btn_update') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
