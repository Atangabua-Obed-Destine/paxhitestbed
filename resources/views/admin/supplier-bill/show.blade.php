@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@include('admin.supplier-bill.partials.style')

@php
    $overdue = $row->isApproved() && $owed > 0.005 && $row->due_date->toDateString() < now()->toDateString();
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
                        <h5 class="mb-0">{{ $row->isOpeningBalance() ? __('Opening balance') : __('Supplier Bill') }} {{ $row->number }}
                            @include('admin.supplier-bill.partials.chip', ['state' => $state, 'overdue' => $overdue])</h5>
                        <div class="ap-toolbar">
                            <a href="{{ route($route.'.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                            @if($row->isDraft())
                                @can('supplier-bill-create')
                                <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> {{ __('Edit') }}</a>
                                <form method="post" action="{{ route($route.'.destroy', $row->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Delete this draft?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> {{ __('Delete draft') }}</button>
                                </form>
                                @endcan
                                @can('supplier-bill-approve')
                                <form method="post" action="{{ route($route.'.approve', $row->id) }}" id="approve-form" class="d-none">@csrf</form>
                                <button type="button" class="btn btn-success btn-sm" id="approve-trigger"
                                        data-review-url="{{ route($route.'.preview-approve', $row->id) }}"
                                        data-confirm-form="approve-form" data-confirm-label="{{ __('Approve and post') }}"
                                        data-confirm-check="{{ __('I have checked the supplier, the invoice number, the amounts and the ledger entry, and confirm this bill is correct.') }}">
                                    <i class="fas fa-check"></i> {{ __('Review and approve') }}</button>
                                @endcan
                            @endif
                            @if($row->isApproved() && $owed > 0.005)
                                @can('supplier-payment-create')
                                <a href="{{ route('admin.supplier-payment.create', ['supplier_id' => $row->supplier_id, 'bill_id' => $row->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-money-bill-wave"></i> {{ __('Pay this bill') }}</a>
                                @endcan
                            @endif
                            @if($row->isApproved())
                                @can('supplier-bill-void')
                                <form method="post" action="{{ route($route.'.void', $row->id) }}" id="void-form" class="d-none">@csrf<input type="hidden" name="void_reason"></form>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        data-review-url="{{ route($route.'.preview-void', $row->id) }}"
                                        data-confirm-form="void-form" data-confirm-label="{{ __('Void and reverse') }}"
                                        data-confirm-class="btn-danger" data-needs-reason="1"
                                        data-confirm-check="{{ __('I understand this reverses the bill in the ledger and on its budget lines.') }}">
                                    <i class="fas fa-ban"></i> {{ __('Void') }}</button>
                                @endcan
                            @endif
                        </div>
                    </div>

                    <div class="card-block">
                        @if($row->isDraft())
                        <div class="ap-note is-warn mb-3">{{ __('This is a draft. It posts nothing to the ledger and is not owed until it is approved.') }}</div>
                        @endif
                        @if($duplicate)
                        <div class="ap-note is-bad mb-3">{{ __('Invoice :ref from this supplier is already entered as :number. Approving this one will be refused.', ['ref' => $row->supplier_reference, 'number' => $duplicate->number]) }}
                            <a href="{{ route($route.'.show', $duplicate->id) }}">{{ __('Open it') }}</a></div>
                        @endif
                        @if($row->isVoid())
                        <div class="ap-note is-bad mb-3">{{ __('Voided') }} {{ format_date($row->voided_at, true) }} {{ __('by') }} {{ $row->voider->name ?? '—' }}.
                            {{ __('Reason') }}: {{ $row->void_reason ?: '—' }}</div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <dl class="ap-dl">
                                    <dt>{{ __('Supplier') }}</dt><dd>{{ $row->supplier->title ?? '—' }}
                                        @if($row->supplier && $row->supplier->tax_number)<div class="ap-muted">{{ __('NIU') }} {{ $row->supplier->tax_number }}</div>@endif</dd>
                                    <dt>{{ __('Their invoice') }}</dt><dd>{{ $row->supplier_reference ?: '—' }}</dd>
                                    <dt>{{ __('Bill date') }}</dt><dd>{{ format_date($row->bill_date) }}</dd>
                                    <dt>{{ __('Due date') }}</dt><dd>{{ format_date($row->due_date) }}</dd>
                                    <dt>{{ __('Description') }}</dt><dd>{{ $row->description ?: '—' }}</dd>
                                    @if($row->attach)
                                    <dt>{{ __('Scanned invoice') }}</dt><dd><a href="{{ asset('uploads/'.$path.'/'.$row->attach) }}" target="_blank"><i class="fas fa-paperclip"></i> {{ __('Open') }}</a></dd>
                                    @endif
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="ap-dl">
                                    <dt>{{ __('Total') }}</dt><dd class="ap-num" style="text-align:left">{{ format_amount($row->total) }}</dd>
                                    <dt>{{ __('Paid') }}</dt><dd>{{ $row->isApproved() ? format_amount((float) $row->total - $owed) : '—' }}</dd>
                                    <dt>{{ __('Still owed') }}</dt><dd><strong>{{ $row->isApproved() ? format_amount($owed) : '—' }}</strong></dd>
                                    <dt>{{ __('Entered') }}</dt><dd>{{ $row->creator->name ?? '—' }}, {{ format_date($row->created_at, true) }}</dd>
                                    @if($row->approved_at)
                                    <dt>{{ __('Approved') }}</dt><dd>{{ $row->approver->name ?? '—' }}, {{ format_date($row->approved_at, true) }}</dd>
                                    @endif
                                    @if($row->journalEntry)
                                    <dt>{{ __('Ledger') }}</dt><dd>
                                        <a href="{{ route('admin.journal-entries.show', $row->journalEntry->id) }}">{{ $row->journalEntry->entry_number }}</a>
                                        @if($row->voidJournalEntry) · {{ __('reversed by') }} <a href="{{ route('admin.journal-entries.show', $row->voidJournalEntry->id) }}">{{ $row->voidJournalEntry->entry_number }}</a>@endif
                                        @if($row->payableAccount)<div class="ap-muted">{{ __('Owed on') }} {{ $row->payableAccount->account_code }} {{ $row->payableAccount->account_name }}</div>@endif
                                    </dd>
                                    @endif
                                </dl>
                            </div>
                        </div>

                        <h6 class="mt-4">{{ __('Lines') }}</h6>
                        <div class="table-responsive">
                            <table class="table ap-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ $row->isOpeningBalance() ? __('Set against') : __('Expense category') }}</th>
                                        <th>{{ __('Description') }}</th>
                                        <th>{{ __('Ledger account') }}</th>
                                        <th class="ap-num">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row->lines as $line)
                                    <tr>
                                        <td>{{ $line->line_number }}</td>
                                        <td>{{ $line->category->title ?? ($row->isOpeningBalance() ? __('Opening balance') : '—') }}</td>
                                        <td>{{ $line->description ?: '—' }}</td>
                                        <td>{{ $line->account ? $line->account->account_code.' '.$line->account->account_name : __('Decided on approval') }}</td>
                                        <td class="ap-num">{{ format_amount($line->amount) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr class="ap-total-row">
                                        <td colspan="4" class="text-right">{{ __('Total') }}</td>
                                        <td class="ap-num">{{ format_amount($row->total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        @if($row->allocations->isNotEmpty())
                        <h6 class="mt-4">{{ __('Payments') }}</h6>
                        <div class="table-responsive">
                            <table class="table ap-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Payment') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th class="ap-num">{{ __('Applied to this bill') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row->allocations as $a)
                                    <tr>
                                        <td><a href="{{ route('admin.supplier-payment.show', $a->supplier_payment_id) }}">{{ $a->payment->number ?? '#' }}</a></td>
                                        <td>{{ $a->payment ? format_date($a->payment->payment_date) : '—' }}</td>
                                        <td>{{ $a->payment->reference ?? '—' }}</td>
                                        <td class="ap-num">{{ format_amount($a->amount) }}</td>
                                        <td>@if($a->payment && $a->payment->isVoid())<span class="ap-chip void">{{ __('Void') }}</span>@else<span class="ap-chip posted">{{ __('Posted') }}</span>@endif</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.supplier-bill.partials.review-modal')

@if(request('review') === 'approve' && $row->isDraft())
@can('supplier-bill-approve')
@push('scripts')
<script>$(function () { $('#approve-trigger').trigger('click'); });</script>
@endpush
@endcan
@endif

@endsection
