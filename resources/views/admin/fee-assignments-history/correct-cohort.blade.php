@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{-- Correcting one amount across a whole intake.

     A wrong figure comes from the programme's fee structure, so it is wrong for
     every student raised from it at once. Correcting them one row at a time on
     the history screen is twenty careful edits of live money; this is the same
     rule applied to the whole cohort, with each student's position shown before
     anything is written. --}}

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-md-12">

                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Correct a fee for a programme') }}</h5>
                        <span class="text-muted">
                            {{ __('Fixing the programme fee structure does not change fees already raised from it. This does.') }}
                        </span>
                    </div>

                    <div class="card-block">
                        <form method="GET" action="{{ route($route.'.cohort') }}">
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label for="category">{{ __('field_fees_type') }} <span>*</span></label>
                                    <select class="form-control" name="category" id="category" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ (string) $selected_category === (string) $category->id ? 'selected' : '' }}>
                                                {{ $category->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="program">{{ __('field_program') }} <span>*</span></label>
                                    <select class="form-control" name="program" id="program" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($programs as $program)
                                            <option value="{{ $program->id }}" {{ (string) $selected_program === (string) $program->id ? 'selected' : '' }}>
                                                {{ $program->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="session">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session" id="session">
                                        <option value="">{{ __('All') }}</option>
                                        @foreach($sessions as $session)
                                            <option value="{{ $session->id }}" {{ (string) $selected_session === (string) $session->id ? 'selected' : '' }}>
                                                {{ $session->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="new_amount">{{ __('New amount') }}</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control"
                                           name="new_amount" id="new_amount" value="{{ $new_amount }}">
                                    <small class="form-text text-muted">{{ __('Leave blank to leave amounts alone.') }}</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label for="new_due_date">{{ __('New deadline') }}</label>
                                    <input type="date" class="form-control"
                                           name="new_due_date" id="new_due_date" value="{{ $new_due_date }}">
                                    <small class="form-text text-muted">{{ __('Leave blank to leave deadlines alone.') }}</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-search"></i> {{ __('btn_filter') }}
                            </button>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                        </form>
                    </div>
                </div>

                @if($rows->isNotEmpty())
                @php
                    // A row is worth ticking if EITHER correction would land on
                    // it. Each is applied on its own, so one being blocked does
                    // not hold the other back.
                    $canAmount = fn ($row) => $row['would_change'] && empty($row['blockers']);
                    $canDue = fn ($row) => !empty($row['due_would_change']) && empty($row['due_blockers']);
                    $changeable = $rows->filter(fn ($row) => $canAmount($row) || $canDue($row));
                    $blocked = $rows->reject(fn ($row) => $canAmount($row) || $canDue($row));
                @endphp

                <form method="POST" action="{{ route($route.'.cohort.apply') }}" id="cohort-form">
                    @csrf
                    <input type="hidden" name="fee_amount" value="{{ $new_amount }}">
                    <input type="hidden" name="due_date" value="{{ $new_due_date }}">

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ $rows->count() }} {{ __('assignment(s)') }}</h5>
                            @if($new_amount || $new_due_date)
                                <span class="text-muted">
                                    {{ __(':n can be corrected, :b cannot', ['n' => $changeable->count(), 'b' => $blocked->count()]) }}
                                </span>
                            @endif
                        </div>

                        <div class="card-block table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:36px">
                                            @if($new_amount || $new_due_date)
                                                <input type="checkbox" id="tick-all" title="{{ __('Select all that can be corrected') }}">
                                            @endif
                                        </th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th class="text-right">{{ __('Current') }}</th>
                                        <th class="text-right">{{ __('field_already_paid') }}</th>
                                        <th class="text-right">{{ __('Outstanding after') }}</th>
                                        <th>{{ __('Deadline') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                    @php
                                        $fee = $row['fee'];
                                        $student = optional($fee->studentEnroll)->student;
                                        $paid = (float) $fee->paid_amount;
                                        $after = $new_amount ? max(0, (float) $new_amount - $paid) : null;
                                        $rowAmountOk = $canAmount($row);
                                        $rowDueOk = $canDue($row);
                                        $rowReasons = array_values(array_unique(array_merge(
                                            $rowAmountOk ? [] : ($row['blockers'] ?? []),
                                            $rowDueOk ? [] : ($row['due_blockers'] ?? [])
                                        )));
                                    @endphp
                                    <tr class="{{ ($rowAmountOk || $rowDueOk) ? '' : 'table-warning' }}">
                                        <td>
                                            @if($rowAmountOk || $rowDueOk)
                                                <input type="checkbox" class="tick-fee" name="fees[]" value="{{ $fee->id }}" checked>
                                            @endif
                                        </td>
                                        <td>{{ optional($fee->studentEnroll)->matricule ?? optional($student)->student_id }}</td>
                                        <td>{{ trim(optional($student)->first_name . ' ' . optional($student)->last_name) }}</td>
                                        <td>{{ optional(optional($fee->studentEnroll)->session)->title }}</td>
                                        <td class="text-right">{{ number_format($fee->fee_amount, 2) }}</td>
                                        <td class="text-right">{{ number_format($paid, 2) }}</td>
                                        <td class="text-right">
                                            {{ $after === null ? '—' : number_format($after, 2) }}
                                        </td>
                                        <td class="text-nowrap">
                                            {{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('d M Y') : __('Not set') }}
                                            @if($new_due_date && $row['due_would_change'])
                                                <i class="fas fa-arrow-right text-muted mx-1"></i>
                                                <span class="fw-bold">{{ \Carbon\Carbon::parse($new_due_date)->format('d M Y') }}</span>
                                            @endif
                                            @if($row['fine_after'] !== null && abs($row['fine_after'] - $row['fine_now']) > 0.005)
                                                {{-- The part that is actually money. --}}
                                                <small class="d-block {{ $row['fine_after'] > $row['fine_now'] ? 'text-danger' : 'text-success' }}">
                                                    {{ __('late fee') }}
                                                    {{ number_format($row['fine_now'], 2) }}
                                                    &rarr;
                                                    {{ number_format($row['fine_after'], 2) }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($rowAmountOk || $rowDueOk)
                                                <span class="badge badge-success">
                                                    @if($rowAmountOk && $rowDueOk)
                                                        {{ __('Amount and deadline') }}
                                                    @elseif($rowAmountOk)
                                                        {{ __('Amount only') }}
                                                    @else
                                                        {{ __('Deadline only') }}
                                                    @endif
                                                </span>
                                                @if($rowReasons)
                                                    <small class="d-block text-muted">{{ implode(' ', $rowReasons) }}</small>
                                                @endif
                                            @elseif(!$new_amount && !$new_due_date)
                                                <span class="badge badge-light">{{ __('Nothing proposed yet') }}</span>
                                            @elseif($rowReasons)
                                                {{-- Named, not counted. A bursar needs to know which student to
                                                     go and look at and why. --}}
                                                <span class="badge badge-warning">{{ __('Cannot be corrected') }}</span>
                                                <small class="d-block text-muted">{{ implode(' ', $rowReasons) }}</small>
                                            @else
                                                <span class="badge badge-light">{{ __('Already as proposed') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if(($new_amount || $new_due_date) && $changeable->isNotEmpty())
                        <div class="card-block border-top">
                            <div class="alert alert-warning">
                                <h6 class="alert-heading mb-1">
                                    <i class="fas fa-exclamation-triangle"></i> {{ __('This changes what these students owe') }}
                                </h6>
                                @if($new_amount)
                                    <p class="mb-1">
                                        {{ __('Each ticked fee is set to :amount. Nobody is charged twice and no payment is moved — only the amount being asked for changes. Where a student has unspent credit, it is applied to the increase.', ['amount' => number_format((float) $new_amount, 2)]) }}
                                    </p>
                                @endif
                                @if($new_due_date)
                                    <p class="mb-0">
                                        {{ __('Each ticked fee falls due on :date. Nothing already paid changes, but the deadline decides the late fee charged when they next pay — the column above shows where that moves.', ['date' => \Carbon\Carbon::parse($new_due_date)->format('d M Y')]) }}
                                    </p>
                                @endif
                            </div>

                            <div class="form-group">
                                <label for="reason">{{ __('Why') }} <span>*</span></label>
                                <input type="text" class="form-control" name="reason" id="reason"
                                       minlength="3" maxlength="500" required
                                       placeholder="{{ __('e.g. wrong amount on the programme fee structure') }}">
                                <small class="form-text text-muted">{{ __('Written onto every fee this changes.') }}</small>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="cohort-confirm">
                                <label class="form-check-label" for="cohort-confirm">
                                    {{ __('I have checked this against the programme fee structure and it is correct.') }}
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary" id="cohort-submit" disabled>
                                <i class="fas fa-check"></i>
                                {{ __('Correct the ticked fees') }}
                            </button>
                        </div>
                        @endif
                    </div>
                </form>
                @elseif($selected_category && $selected_program)
                    <div class="alert alert-info">
                        {{ __('No fee assignments match that category and programme.') }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    "use strict";

    $('#tick-all').on('change', function () {
        $('.tick-fee').prop('checked', this.checked);
    });

    // A deliberate act, not a reflex: the button stays dead until the tick box
    // is ticked, the same as every other screen here that moves money.
    $('#cohort-confirm').on('change', function () {
        $('#cohort-submit').prop('disabled', !this.checked);
    });

    $('#cohort-form').on('submit', function () {
        if (!$('.tick-fee:checked').length) {
            alert({!! json_encode(__('Tick at least one fee to correct.')) !!});
            return false;
        }

        $('#cohort-submit').prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> ' + {!! json_encode(__('Correcting...')) !!});
    });
})(jQuery);
</script>

@endsection
