{{--
  Recording or correcting one income or one expense.

  The same eleven fields the form always had, grouped into the three questions
  the Bursar is actually answering — what it is, how it was paid, what proves
  it. One partial for all four forms, because the four files had already
  drifted: the income edit form was offering a different list of payment
  methods than the income create form that wrote the values.

  Expects:
    $mode      'create' or 'edit'
    $row       the model on edit, absent on create
    $title, $route, $path       the module's own, as the controllers pass them
    $extra     optional view name rendered inside "What it is" (the expense
               edit form's budget fields)
    $account_help  lang key for the note under the payment account
  Plus whatever common.inc.budget_line_category needs, which the controllers
  already supply unchanged.
--}}

@include('admin.account.partials.theme')

@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $row = $row ?? null;

    // old() first so a failed validation hands the Bursar back what they
    // typed, then the stored value, then nothing.
    $val = fn ($field, $default = null) => old($field, $row->{$field} ?? $default);

    $attached = $isEdit && $row->attach && is_file('uploads/'.$path.'/'.$row->attach);
@endphp

<div class="main-body acct-form">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-lg-10 col-xl-9">
                <div class="acct-panel">
                    <div class="acct-sec" style="padding-bottom:0;">
                        <div class="acct-label">{{ $title }}</div>
                        <h4 style="margin:.2rem 0 0;font-size:1.1rem;font-weight:700;color:var(--ink);">
                            {{ $isEdit ? __('acc_form_head_edit') : __('acc_form_head_create') }}
                        </h4>
                        @if($isEdit)
                        <div style="font-size:.78rem;color:var(--muted);margin-top:.2rem;">
                            {{ __('acc_form_recorded') }}
                            {{ \Carbon\Carbon::parse($row->created_at)->format('j M Y') }}
                            @if($row->recordedBy) · {{ $row->recordedBy->name }} @endif
                        </div>
                        @endif
                    </div>

                    <form class="needs-validation" novalidate method="post" enctype="multipart/form-data"
                          action="{{ $isEdit ? route($route.'.update', [$row->id]) : route($route.'.store') }}">
                        @csrf
                        @if($isEdit) @method('PUT') @endif

                        <div class="acct-sec">
                            <div class="acct-sec-head">{{ __('acc_section_what') }}</div>
                            <div class="row">
                                {{-- Heading, then the categories filed under it. The
                                     Income & Expenditure sheet's own order, so a figure
                                     cannot be recorded against a category that reaches
                                     no line of the sheet. --}}
                                @include('common.inc.budget_line_category')

                                @isset($extra)
                                    @include($extra)
                                @endisset

                                <div class="form-group col-md-4">
                                    <label for="title">{{ __('field_title') }} <span>*</span></label>
                                    <input type="text" class="form-control" name="title" id="title"
                                           value="{{ $val('title') }}" required>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_title') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="amount">{{ __('field_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                                    {{-- At full precision — and NOT class="autonumber".

                                         Taking round() out of the blade was only half of
                                         it: the shared .autonumber initialiser in
                                         footer_script runs AutoNumeric with
                                         decimalPlaces: 0, so the browser rounded the
                                         field again on load whatever the server sent. A
                                         record of 123,333.33 opened as 123,333 and saved
                                         that way, re-posting the ledger at the rounded
                                         figure, because amount is in the observer's watch
                                         list.

                                         Its own class and its own initialiser below, so
                                         the fix holds in the browser without changing the
                                         decimal places every other module's money field
                                         has always used. --}}
                                    <input type="text" class="form-control acct-amount" name="amount" id="amount"
                                           value="{{ $val('amount') }}" required
                                           inputmode="decimal" autocomplete="off">
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_amount') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="date">{{ __('field_date') }} <span>*</span></label>
                                    <input type="date" class="form-control date" name="date" id="date"
                                           value="{{ $isEdit
                                               ? old('date', \Carbon\Carbon::parse($row->date)->format('Y-m-d'))
                                               : old('date', date('Y-m-d')) }}" required>
                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_date') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="acct-sec">
                            <div class="acct-sec-head">{{ __('acc_section_how') }}</div>
                            <div class="row">
                                @include('common.inc.payment_method_select', ['pm_selected' => $val('payment_method')])

                                <div class="form-group col-md-4">
                                    <label for="payment_account_id" class="form-label">{{ __('field_payment_account') }}</label>

                                    @if($isEdit)
                                        {{-- Shown, not offered. update() does not save this
                                             field and does not adjust the account's balance
                                             or its transaction row, so an editable control
                                             here would accept a change and silently drop
                                             it. Correcting that is its own piece of work. --}}
                                        <input type="text" class="form-control" disabled
                                               value="{{ $row->paymentAccount->title ?? __('acc_not_recorded') }}">
                                    @else
                                        <select class="form-control" name="payment_account_id" id="payment_account_id">
                                            <option value="">{{ __('select_payment_account_optional') }}</option>
                                            @foreach(\App\Models\PaymentAccount::where('status', 1)->orderBy('title')->get() as $account)
                                            <option value="{{ $account->id }}" @if($val('payment_account_id') == $account->id) selected @endif>
                                                {{ $account->title }} — {{ __('field_balance') }}: {!! $setting->currency_symbol !!}{{ number_format($account->current_balance, 2) }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @isset($account_help)
                                        <small class="form-text text-muted">{{ __($account_help) }}</small>
                                        @endisset
                                    @endif
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="invoice_id">{{ __('field_invoice_id') }}</label>
                                    <input type="text" class="form-control" name="invoice_id" id="invoice_id"
                                           value="{{ $val('invoice_id') }}">
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="reference">{{ __('field_reference') }}</label>
                                    <input type="text" class="form-control" name="reference" id="reference"
                                           value="{{ $val('reference') }}">
                                </div>
                            </div>
                        </div>

                        <div class="acct-sec">
                            <div class="acct-sec-head">{{ __('acc_section_evidence') }}</div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="attach">{{ __('field_attach') }}</label>
                                    <input type="file" class="form-control" name="attach" id="attach">

                                    {{-- An empty file input gave no clue whether a receipt
                                         was already on the record, so the only way to find
                                         out was to leave and look at the list. --}}
                                    @if($isEdit)
                                        @if($attached)
                                        <div class="acct-file">
                                            <i class="fas fa-paperclip"></i>
                                            <span>{{ __('acc_attached_file') }}:</span>
                                            <a href="{{ asset('uploads/'.$path.'/'.$row->attach) }}" target="_blank">{{ $row->attach }}</a>
                                        </div>
                                        <small class="form-text text-muted">{{ __('acc_replace_file') }}</small>
                                        @else
                                        <div class="acct-file is-empty">
                                            <i class="fas fa-paperclip"></i>
                                            <span>{{ __('acc_no_attachment') }}</span>
                                        </div>
                                        @endif
                                    @endif
                                </div>

                                <div class="form-group col-md-8">
                                    <label for="note">{{ __('field_note') }}</label>
                                    <textarea class="form-control" name="note" id="note" rows="3">{{ $val('note') }}</textarea>
                                </div>
                            </div>
                        </div>

                        @include('admin.account.partials.confirm_modal')

                        <div class="acct-foot">
                            {{-- type="button": nothing is recorded until the figures
                                 have been read back, the same shape the fees receipt
                                 screen uses. The real submit lives in the modal. --}}
                            <button type="button" class="acct-btn acct-btn-main" id="acct-review">
                                <i class="fas fa-check"></i>{{ __('btn_save') }}
                            </button>

                            <a href="{{ route($route.'.index') }}" class="acct-btn acct-cancel">
                                <i class="fas fa-arrow-left"></i>{{ __('btn_back') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
