@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{--
    One payment plan agreement: where it stands, what it covers, and — when it
    is back for approval — exactly what changed since it was last approved.
--}}
@php
    $student = $agreement->student;
    $money = fn ($v) => number_format((float) $v, 2);
    $timeline = $agreement->timeline();
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">
                            {{ __('Payment Plan Agreement') }} {{ $agreement->reference }}
                            <span class="badge bg-{{ $agreement->status_class }} badge-{{ $agreement->status_class }} ms-2">{{ $agreement->status_label }}</span>
                            @if($agreement->version > 1)
                                <small class="text-muted ms-1">{{ __('Revision :n', ['n' => $agreement->version]) }}</small>
                            @endif
                        </h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.payment-plan.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>
                            <a href="{{ route('admin.payment-plan.agreement.print', $agreement->id) }}" class="btn btn-primary btn-sm" id="print-agreement">
                                <i class="fas fa-print"></i> {{ __('Print agreement') }}
                            </a>
                            @if(!$agreement->isClosed())
                                @can('payment-plan.edit')
                                <a href="{{ route('admin.payment-plan.agreement.revise', $agreement->id) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> {{ __('Revise schedule') }}</a>
                                @endcan
                            @endif
                        </div>
                    </div>

                    <div class="card-block">

                        @if($agreement->isPending())
                            <div class="alert alert-warning">
                                <h6 class="alert-heading mb-1">
                                    <i class="fas fa-hourglass-half"></i>
                                    {{ $agreement->wasApproved() ? __('Back with the Vice Chancellor for re-approval') : __('Awaiting the Vice Chancellor\'s approval') }}
                                </h6>
                                <p class="mb-0">{{ __('No payment can be taken on the fees below until this agreement is approved or rejected.') }}</p>
                                @if($agreement->review_reason)
                                    <p class="mb-0 mt-1"><strong>{{ __('Why it came back') }}:</strong> {{ $agreement->review_reason }}</p>
                                @endif
                            </div>
                        @endif

                        @if($changes)
                            {{-- What the approver is being asked to approve again. --}}
                            <div class="alert alert-info" id="changes-since-approval">
                                <h6 class="alert-heading mb-1"><i class="fas fa-exchange-alt"></i> {{ __('Changed since it was last approved') }}</h6>
                                <ul class="mb-0 ps-3">
                                    @foreach($changes as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($mismatches)
                            <div class="alert alert-danger" id="mismatches">
                                <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-circle"></i> {{ __('The schedule no longer matches the fees') }}</h6>
                                <ul class="mb-0 ps-3">
                                    @foreach($mismatches as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($agreement->status === 'rejected')
                            <div class="alert alert-danger">
                                <strong>{{ __('Rejected') }}</strong>
                                {{ __('by :who on :when', ['who' => optional($agreement->rejecter)->name ?? '—', 'when' => optional($agreement->rejected_at)->format('d M Y H:i')]) }}:
                                {{ $agreement->rejection_reason }}
                            </div>
                        @elseif($agreement->status === 'cancelled')
                            <div class="alert alert-secondary"><strong>{{ __('status_cancelled') }}:</strong> {{ $agreement->rejection_reason }}</div>
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm mb-0">
                                    <tr><th class="w-50">{{ __('field_student') }}</th><td>{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }} <span class="text-muted">#{{ $student->student_id ?? '' }}</span></td></tr>
                                    <tr><th>{{ __('Prepared by') }}</th><td>{{ optional($agreement->creator)->name ?? '—' }} <span class="text-muted">{{ optional($agreement->created_at)->format('d M Y H:i') }}</span></td></tr>
                                    <tr><th>{{ __('Approved by') }}</th><td>
                                        @if($agreement->approver)
                                            {{ $agreement->approver->name }} <span class="text-muted">{{ optional($agreement->approved_at)->format('d M Y H:i') }}</span>
                                            @if($agreement->isPending()) <span class="text-muted small">({{ __('an earlier version') }})</span> @endif
                                        @else — @endif
                                    </td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm mb-0">
                                    <tr><th class="w-50">{{ __('Agreement total') }}</th><td><strong>{{ $currency }} {{ $money($agreement->total) }}</strong></td></tr>
                                    <tr><th>{{ __('field_installments') }}</th><td>{{ $timeline->count() }}</td></tr>
                                    @if($agreement->notes)<tr><th>{{ __('field_notes') }}</th><td>{{ $agreement->notes }}</td></tr>@endif
                                </table>
                            </div>
                        </div>

                        <h6>{{ __('Fees on this agreement') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th class="text-end">{{ __('field_net_amount') }}</th>
                                        <th class="text-end">{{ __('Planned') }}</th>
                                        <th class="text-end">{{ __('Paid on the plan') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($agreement->plans as $plan)
                                    <tr>
                                        <td>{{ optional(optional($plan->fee)->category)->title ?? '—' }}</td>
                                        <td class="text-end">{{ $money(optional($plan->fee)->total_amount) }}</td>
                                        <td class="text-end">{{ $money($plan->total_amount) }}</td>
                                        <td class="text-end">{{ $money($plan->total_paid) }}</td>
                                        <td>{!! $plan->status_badge !!}</td>
                                        <td><a href="{{ route('admin.payment-plan.show', $plan->id) }}" class="btn btn-sm btn-light"><i class="fas fa-eye"></i></a></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <h6>{{ __('Schedule') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_due_date') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th class="text-end">{{ __('field_amount') }}</th>
                                        <th class="text-end">{{ __('field_paid_amount') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($timeline as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ optional($row['installment']->due_date)->format('d M Y') }}</td>
                                        <td>{{ optional(optional($row['fee'])->category)->title }} <span class="text-muted small">{{ __('instalment :n', ['n' => $row['installment']->installment_number]) }}</span></td>
                                        <td class="text-end">{{ $money($row['installment']->amount) }}</td>
                                        <td class="text-end">{{ $money($row['installment']->paid_amount) }}</td>
                                        <td>{!! $row['installment']->status_badge !!}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- The decision. Recorded by whoever holds the approve
                             permission — the Vice Chancellor's role does. --}}
                        @if($agreement->isPending())
                            @can('payment-plan.approve')
                            <div class="card border-primary mt-3" id="decision">
                                <div class="card-body">
                                    <h6>{{ __('Decision') }}</h6>
                                    @if($approvalBlockers)
                                        <p class="text-danger small mb-2">{{ __('It cannot be approved until the schedule matches the fees. Revise it first.') }}</p>
                                    @else
                                        <p class="text-muted small mb-2">{{ __('Approve once the printed agreement has been signed. Its instalments can then be paid.') }}</p>
                                    @endif
                                    <div class="d-flex gap-2 flex-wrap align-items-start">
                                        <form method="post" action="{{ route('admin.payment-plan.agreement.approve', $agreement->id) }}"
                                              onsubmit="return confirm(@json(__('Approve agreement :ref?', ['ref' => $agreement->reference])));">
                                            @csrf
                                            <button type="submit" class="btn btn-success" {{ $approvalBlockers ? 'disabled' : '' }}>
                                                <i class="fas fa-check"></i> {{ __('Approve') }}
                                            </button>
                                        </form>
                                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#reject-form">
                                            <i class="fas fa-times"></i> {{ __('Reject') }}
                                        </button>
                                    </div>
                                    <form method="post" action="{{ route('admin.payment-plan.agreement.reject', $agreement->id) }}" class="collapse mt-2" id="reject-form">
                                        @csrf
                                        <label class="form-label small">{{ __('Why it is rejected') }} <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="form-control" rows="2" required></textarea>
                                        <small class="text-muted d-block mb-2">{{ __('Rejecting ends the agreement. Its fees can be paid in the ordinary way again; money already paid on it stays on the fees.') }}</small>
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Reject agreement') }}</button>
                                    </form>
                                </div>
                            </div>
                            @endcan
                        @endif

                        @if(!$agreement->isClosed())
                            @can('payment-plan.cancel')
                            <details class="mt-3">
                                <summary class="text-danger small">{{ __('Cancel this agreement') }}</summary>
                                <form method="post" action="{{ route('admin.payment-plan.agreement.cancel', $agreement->id) }}" class="mt-2">
                                    @csrf
                                    <textarea name="reason" class="form-control" rows="2" required placeholder="{{ __('Why it is cancelled') }}"></textarea>
                                    <button type="submit" class="btn btn-outline-danger btn-sm mt-2">{{ __('Cancel agreement') }}</button>
                                </form>
                            </details>
                            @endcan
                        @endif

                        <h6 class="mt-4">{{ __('History') }}</h6>
                        <ul class="list-unstyled small" id="history">
                            @foreach($agreement->events->sortByDesc('id') as $event)
                            <li class="mb-2 border-start ps-2">
                                <strong>{{ $event->label }}</strong>
                                <span class="text-muted">· {{ __('Revision :n', ['n' => $event->version]) }} · {{ optional($event->user)->name ?? __('System') }} · {{ optional($event->created_at)->format('d M Y H:i') }}</span>
                                @if($event->reason)<div>{{ $event->reason }}</div>@endif
                                @if($event->changes)
                                    <ul class="mb-0 ps-3">@foreach($event->changes as $line)<li>{{ $line }}</li>@endforeach</ul>
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
