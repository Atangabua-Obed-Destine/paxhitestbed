@extends($print ? 'admin.supplier-bill.print' : 'admin.layouts.master')
@section('title', $title)
@section('content')

@unless($print)
@include('admin.supplier-bill.partials.style')
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
                        <h5 class="mb-0">{{ __('Supplier Payment') }} {{ $row->number }}
                            @if($row->isVoid())<span class="ap-chip void">{{ __('Void') }}</span>@else<span class="ap-chip posted">{{ __('Posted') }}</span>@endif</h5>
                        <div class="ap-toolbar">
                            <a href="{{ route($route.'.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                            <a href="{{ route($route.'.show', ['id' => $row->id, 'print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> {{ __('Print voucher') }}</a>
                            @if(!$row->isVoid())
                            @can('supplier-payment-void')
                            <form method="post" action="{{ route($route.'.void', $row->id) }}" id="void-form" class="d-none">@csrf<input type="hidden" name="void_reason"></form>
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-review-url="{{ route($route.'.preview-void', $row->id) }}"
                                    data-confirm-form="void-form" data-confirm-label="{{ __('Void and reverse') }}"
                                    data-confirm-class="btn-danger" data-needs-reason="1"
                                    data-confirm-check="{{ __('I understand this reverses the payment, and the bills it paid will be owed again.') }}">
                                <i class="fas fa-ban"></i> {{ __('Void') }}</button>
                            @endcan
                            @endif
                        </div>
                    </div>
                    <div class="card-block">
                        @if($row->isVoid())
                        <div class="ap-note is-bad mb-3">{{ __('Voided') }} {{ format_date($row->voided_at, true) }} {{ __('by') }} {{ $row->voider->name ?? '—' }}.
                            {{ __('Reason') }}: {{ $row->void_reason ?: '—' }}. {{ __('The bills it paid are owed again.') }}</div>
                        @endif
@else
    <h1 class="doc-title">{{ __('Payment Voucher') }} {{ $row->number }}</h1>
    @if($row->isVoid())<div class="doc-sub"><strong>{{ __('VOID') }}</strong> — {{ $row->void_reason }}</div>@endif
@endunless

                        <table class="table ap-table" style="max-width:720px">
                            <tbody>
                                <tr><td style="width:200px">{{ __('Paid to') }}</td><td><strong>{{ $row->supplier->title ?? '—' }}</strong>
                                    @if($row->supplier && $row->supplier->tax_number) · {{ __('NIU') }} {{ $row->supplier->tax_number }}@endif</td></tr>
                                <tr><td>{{ __('Date') }}</td><td>{{ format_date($row->payment_date) }}</td></tr>
                                <tr><td>{{ __('Amount') }}</td><td><strong>{{ format_amount($row->amount) }}</strong></td></tr>
                                <tr><td>{{ __('Method') }}</td><td>{{ \App\Support\PaymentMethod::label($row->payment_method) }}</td></tr>
                                <tr><td>{{ __('Paid from') }}</td><td>{{ $row->cashAccount ? $row->cashAccount->account_code.' '.$row->cashAccount->account_name : '—' }}
                                    @if($row->paymentAccount) · {{ $row->paymentAccount->title }}@endif</td></tr>
                                <tr><td>{{ __('Cheque or transfer number') }}</td><td>{{ $row->reference ?: '—' }}</td></tr>
                                @if($row->note)<tr><td>{{ __('Note') }}</td><td>{{ $row->note }}</td></tr>@endif
                                @unless($print)
                                <tr><td>{{ __('Recorded') }}</td><td>{{ $row->creator->name ?? '—' }}, {{ format_date($row->created_at, true) }}</td></tr>
                                @if($row->journalEntry)
                                <tr><td>{{ __('Ledger') }}</td><td><a href="{{ route('admin.journal-entries.show', $row->journalEntry->id) }}">{{ $row->journalEntry->entry_number }}</a>
                                    @if($row->voidJournalEntry) · {{ __('reversed by') }} <a href="{{ route('admin.journal-entries.show', $row->voidJournalEntry->id) }}">{{ $row->voidJournalEntry->entry_number }}</a>@endif</td></tr>
                                @endif
                                @endunless
                            </tbody>
                        </table>

                        <h6 class="mt-3">{{ __('Bills paid') }}</h6>
                        <table class="table ap-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Bill') }}</th>
                                    <th>{{ __('Their invoice') }}</th>
                                    <th>{{ __('Bill date') }}</th>
                                    <th class="ap-num">{{ __('Bill total') }}</th>
                                    <th class="ap-num">{{ __('Paid by this payment') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($row->allocations as $a)
                                <tr>
                                    <td>@if($print){{ $a->bill->number ?? '' }}@else<a href="{{ route('admin.supplier-bill.show', $a->supplier_bill_id) }}">{{ $a->bill->number ?? '' }}</a>@endif</td>
                                    <td>{{ $a->bill->supplier_reference ?? '—' }}</td>
                                    <td>{{ $a->bill ? format_date($a->bill->bill_date) : '—' }}</td>
                                    <td class="ap-num">{{ $a->bill ? format_amount($a->bill->total) : '—' }}</td>
                                    <td class="ap-num">{{ format_amount($a->amount) }}</td>
                                </tr>
                                @endforeach
                                <tr class="ap-total-row"><td colspan="4">{{ __('Total') }}</td><td class="ap-num">{{ format_amount($row->amount) }}</td></tr>
                            </tbody>
                        </table>

@if($print)
    <div class="sign">
        <div>{{ __('Prepared by') }}<br>{{ $row->creator->name ?? '' }}</div>
        <div>{{ __('Approved by') }}</div>
        <div>{{ __('Received by (supplier)') }}</div>
    </div>
@else
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.supplier-bill.partials.review-modal')
@endif

@endsection
