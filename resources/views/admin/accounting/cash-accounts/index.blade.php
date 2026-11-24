@extends('admin.layouts.master')
@section('title', $title)

@php
    $select = function (string $name, $value, string $id) use ($ledgerAccounts) {
        $html = '<select class="form-control" name="' . e($name) . '" id="' . e($id) . '">';
        $html .= '<option value="">' . e(__('Use the category mapping\'s account')) . '</option>';
        foreach ($ledgerAccounts as $a) {
            $html .= '<option value="' . $a->id . '"' . ((int) $value === (int) $a->id ? ' selected' : '') . '>'
                . e($a->account_code . ' - ' . $a->account_name) . '</option>';
        }

        return $html . '</select>';
    };
    $kinds = [
        'cash_account_id' => [__('Cash'), __('Cash payments')],
        'bank_account_id' => [__('Bank transfer'), __('Bank transfers and cheques')],
        'mobile_money_account_id' => [__('Mobile money'), __('MTN Mobile Money and Orange Money')],
        'other_account_id' => [__('Other'), __('Any other method')],
    ];
@endphp

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <form action="{{ route('admin.accounting.cash-accounts.update') }}" method="post">
            @csrf
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <p class="text-muted">
                                {{ __('A category\'s account mapping decides the income or expense account. These decide the cash side: which cash box, bank or mobile-money account the money actually went through. A transaction that names a payment account posts to that account\'s ledger account; otherwise it posts to the default for its payment method; otherwise to the account in the category mapping, as before.') }}
                            </p>

                            <h6 class="mt-3">{{ __('Default ledger account for each payment method') }}</h6>
                            <div class="row">
                                @foreach($kinds as $field => [$label, $hint])
                                <div class="form-group col-md-6 col-lg-3">
                                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                    {!! $select($field, old($field, $setting->{$field}), $field) !!}
                                    <small class="text-muted">{{ $hint }}</small>
                                    @error($field)<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                @endforeach
                            </div>

                            <h6 class="mt-4">{{ __('Payment accounts') }}</h6>
                            @if($paymentAccounts->isEmpty())
                                <p class="text-muted">
                                    {{ __('No payment accounts yet. Create the school\'s cash boxes, bank accounts and wallets under Finance > Payment accounts, then link each one here.') }}
                                </p>
                            @else
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Payment account') }}</th>
                                            <th>{{ __('Ledger account') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($paymentAccounts as $pa)
                                        <tr>
                                            <td>{{ $pa->title }}@if($pa->account_number)<br><small class="text-muted">{{ $pa->account_number }}</small>@endif</td>
                                            <td style="min-width: 260px;">{!! $select('links[' . $pa->id . ']', old('links.' . $pa->id, $pa->chart_of_account_id), 'link_' . $pa->id) !!}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('btn_save') }}</button>
                        </div>
                    </div>
                </div>

                @if($comparison->isNotEmpty())
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('Books beside the ledger') }}</h5>
                        </div>
                        <div class="card-block">
                            <p class="text-muted small">
                                {{ __('The balance the payment accounts show, beside the balance of the ledger account they are linked to. A difference is expected where the ledger holds money posted before the link, or money recorded without choosing a payment account.') }}
                            </p>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Ledger account') }}</th>
                                            <th>{{ __('Payment accounts') }}</th>
                                            <th class="text-right">{{ __('Books') }}</th>
                                            <th class="text-right">{{ __('Ledger') }}</th>
                                            <th class="text-right">{{ __('Difference') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($comparison as $row)
                                        <tr>
                                            <td>{{ $row->account_code }} - {{ $row->account_name }}</td>
                                            <td>{{ $row->books }}</td>
                                            <td class="text-right">{{ format_amount($row->book_balance) }}</td>
                                            <td class="text-right">{{ format_amount($row->ledger_balance) }}</td>
                                            <td class="text-right {{ abs($row->difference) >= 1 ? 'text-danger' : 'text-success' }}">{{ format_amount($row->difference) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
