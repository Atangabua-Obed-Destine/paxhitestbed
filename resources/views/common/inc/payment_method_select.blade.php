{{--
  How the money moved.

  One partial for all four forms, because when each one carried its own list
  they drifted: the create forms stored one numbering while the index, the show
  modal and the income edit form read an older one, so a Bank Transfer
  displayed as "Cash", the income edit dropdown offered "Cash" twice, and
  Orange Money could not be chosen at all. App\Support\PaymentMethod is the one
  vocabulary now; nothing here hardcodes a label.

  Expects $pm_selected — the stored value to preselect, or null.
--}}

<div class="form-group col-md-4">
    <label for="payment_method" class="form-label">
        {{ __('field_payment_method') }} <span>*</span>
    </label>

    <select class="form-control" name="payment_method" id="payment_method" required>
        <option value="">{{ __('select') }}</option>
        @foreach( \App\Support\PaymentMethod::options() as $pmValue => $pmLabel )
        <option value="{{ $pmValue }}" @if( (string) ($pm_selected ?? '') === (string) $pmValue ) selected @endif>{{ $pmLabel }}</option>
        @endforeach
    </select>

    <div class="invalid-feedback">
        {{ __('required_field') }} {{ __('field_payment_method') }}
    </div>
</div>
