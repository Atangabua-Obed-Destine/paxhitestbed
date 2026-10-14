@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@php
    $currency = $setting->currency_symbol ?? '';
    $dec      = $setting->decimal_place ?? 2;
    $df       = $setting->date_format ?? 'Y-m-d';

    $fmt = function ($n) use ($dec) { return number_format((float) $n, $dec, '.', ','); };
@endphp

<div class="main-body">
    <div class="page-wrapper">

        <!-- Header -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">
                                <i class="fas fa-stream"></i>
                                {{ $title }}
                            </h5>
                            <p class="text-muted m-0">
                                {{ __('every_fee_assignment_regardless_of_origin') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-info">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_assignments') }}</h6>
                        <h3 class="text-white mb-0">{{ number_format($stats['total_assignments']) }}</h3>
                        <small>{{ $stats['distinct_students'] }} {{ __('students') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-primary">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_amount_due') }}</h6>
                        <h3 class="text-white mb-0">{{ $fmt($stats['total_amount']) }} {!! $currency !!}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-success">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('total_collected') }}</h6>
                        <h3 class="text-white mb-0">{{ $fmt($stats['total_paid']) }} {!! $currency !!}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card text-white bg-danger">
                    <div class="card-block">
                        <h6 class="text-white">{{ __('outstanding') }}</h6>
                        <h3 class="text-white mb-0">{{ $fmt($stats['total_outstanding']) }} {!! $currency !!}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Origin breakdown chips -->
        @if(($stats['by_origin'] ?? collect())->count() > 0)
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-block py-2">
                        <strong class="mr-2">{{ __('breakdown_by_origin') }}:</strong>
                        @foreach($stats['by_origin'] as $key => $count)
                            @php $label = $origins[$key] ?? ucfirst(str_replace('_',' ',$key)); @endphp
                            <span class="badge {{ ['resit'=>'badge-danger','payment_plan'=>'badge-warning','fees_master'=>'badge-secondary','program_semester_fee'=>'badge-info','auto_progression'=>'badge-primary','transfer_in'=>'badge-dark','manual'=>'badge-success'][$key] ?? 'badge-light' }} mr-1">
                                {{ $label }}: {{ $count }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5>{{ __('filter_assignments') }}</h5></div>
                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.index') }}" class="needs-validation" novalidate>
                            <div class="row gx-2">
                                @include('common.inc.fees_search_filter')

                                <div class="form-group col-md-3">
                                    <label for="category">{{ __('field_fees_type') }}</label>
                                    <select class="form-control" name="category" id="category">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ $selected_category == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="origin">{{ __('assignment_origin') }}</label>
                                    <select class="form-control" name="origin" id="origin">
                                        @foreach($origins as $key => $label)
                                            <option value="{{ $key }}" {{ $selected_origin == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="payment_status">{{ __('payment_status') }}</label>
                                    <select class="form-control" name="payment_status" id="payment_status">
                                        <option value="all" {{ $selected_payment_status == 'all' ? 'selected' : '' }}>{{ __('all') }}</option>
                                        <option value="0" {{ $selected_payment_status == '0' ? 'selected' : '' }}>{{ __('unpaid') }}</option>
                                        <option value="2" {{ $selected_payment_status == '2' ? 'selected' : '' }}>{{ __('partially_paid') }}</option>
                                        <option value="1" {{ $selected_payment_status == '1' ? 'selected' : '' }}>{{ __('fully_paid') }}</option>
                                        <option value="5" {{ $selected_payment_status == '5' ? 'selected' : '' }}>{{ __('overpaid') }}</option>
                                        <option value="4" {{ $selected_payment_status == '4' ? 'selected' : '' }}>{{ __('payment_plan') }}</option>
                                        <option value="3" {{ $selected_payment_status == '3' ? 'selected' : '' }}>{{ __('cancelled') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="student_id">{{ __('student_id_or_name') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id"
                                           value="{{ $selected_student_id }}"
                                           placeholder="{{ __('matricule_or_name') }}">
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="date_from">{{ __('assigned_from') }}</label>
                                    <input type="date" class="form-control" name="date_from" id="date_from" value="{{ $selected_date_from }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="date_to">{{ __('assigned_to') }}</label>
                                    <input type="date" class="form-control" name="date_to" id="date_to" value="{{ $selected_date_to }}">
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="view_mode">{{ __('view') }}</label>
                                    <select class="form-control" name="view_mode" id="view_mode">
                                        <option value="flat"    {{ $selected_view == 'flat'    ? 'selected' : '' }}>{{ __('flat_per_student') }}</option>
                                        <option value="grouped" {{ $selected_view == 'grouped' ? 'selected' : '' }}>{{ __('grouped_by_cohort') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-3 align-self-end">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-search"></i> {{ __('btn_filter') }}
                                    </button>
                                    @can('fee-assignments-history-edit')
                                        <a href="{{ route($route.'.cohort') }}" class="btn btn-warning">
                                            <i class="fas fa-pen"></i> {{ __('Correct a whole programme') }}
                                        </a>
                                    @endcan
                                    <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> {{ __('reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-block">

                        @if($selected_view === 'grouped')
                            {{-- ============== GROUPED VIEW ============== --}}
                            @if($groups->count() === 0)
                                <div class="alert alert-info">{{ __('no_fee_assignments_match_filters') }}</div>
                            @else
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>{{ __('field_fees_type') }}</th>
                                            <th>{{ __('cohort') }}</th>
                                            <th>{{ __('field_amount') }} / {{ __('student') }}</th>
                                            <th>{{ __('students_in_cohort') }}</th>
                                            <th>{{ __('total_due') }}</th>
                                            <th>{{ __('collected') }}</th>
                                            <th>{{ __('outstanding') }}</th>
                                            <th>{{ __('assignment_origin') }}</th>
                                            <th>{{ __('field_assign') }} {{ __('field_date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($groups as $i => $g)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td><strong>{{ $g->category->title ?? '—' }}</strong></td>
                                            <td>
                                                {{ optional($g->program)->title ?? '—' }}<br>
                                                <small class="text-muted">
                                                    {{ optional($g->session)->title ?? '' }}
                                                    @if(optional($g->semester)->title) · {{ $g->semester->title }} @endif
                                                    @if(optional($g->section)->title) · {{ $g->section->title }} @endif
                                                </small>
                                            </td>
                                            <td>{{ $fmt($g->fee_amount) }} {!! $currency !!}</td>
                                            <td><span class="badge badge-pill badge-info">{{ $g->cohort }}</span></td>
                                            <td>{{ $fmt($g->due) }} {!! $currency !!}</td>
                                            <td class="text-success">{{ $fmt($g->paid) }} {!! $currency !!}</td>
                                            <td class="{{ ($g->due - $g->paid) > 0 ? 'text-danger' : 'text-muted' }}">
                                                {{ $fmt(max(0, $g->due - $g->paid)) }} {!! $currency !!}
                                            </td>
                                            <td><span class="badge {{ $g->origin_badge }}">{{ $origins[$g->origin] ?? $g->origin }}</span></td>
                                            <td>
                                                @if($g->assign_date)
                                                    {{ date($df, strtotime($g->assign_date)) }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                        @else
                            {{-- ============== FLAT VIEW ============== --}}
                            @if($rows->count() === 0)
                                <div class="alert alert-info">{{ __('no_fee_assignments_match_filters') }}</div>
                            @else
                            <div class="table-responsive">
                                <table id="basic-table" class="display table table-striped table-hover" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>{{ __('field_id') }}</th>
                                            <th>{{ __('field_student') }}</th>
                                            <th>{{ __('cohort') }}</th>
                                            <th>{{ __('field_fees_type') }}</th>
                                            <th>{{ __('field_amount') }}</th>
                                            <th>{{ __('paid') }}</th>
                                            <th>{{ __('balance') }}</th>
                                            <th>{{ __('field_assign') }} {{ __('field_date') }}</th>
                                            <th>{{ __('field_due_date') }}</th>
                                            <th>{{ __('status') }}</th>
                                            <th>{{ __('assignment_origin') }}</th>
                                            @canany(['fee-assignments-history-delete', 'fee-assignments-history-transfer', 'fee-assignments-history-edit'])
                                            <th class="text-center">{{ __('action') }}</th>
                                            @endcanany
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $i => $row)
                                            @php
                                                $student = optional($row->studentEnroll)->student;
                                                $name = $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '—';
                                                $matricule = optional($row->studentEnroll)->matricule ?? optional($student)->student_id ?? '';
                                                $due  = (float) $row->total_amount;
                                                $paid = (float) ($row->paid_amount ?? 0);
                                                $bal  = max(0, $due - $paid);
                                            @endphp
                                            <tr>
                                                <td><strong>#{{ $row->id }}</strong></td>
                                                <td>
                                                    <strong>{{ $name }}</strong><br>
                                                    <small class="text-muted">{{ $matricule }}</small>
                                                </td>
                                                <td>
                                                    <small>
                                                        {{ optional(optional($row->studentEnroll)->program)->title ?? '' }}<br>
                                                        <span class="text-muted">
                                                            {{ optional(optional($row->studentEnroll)->session)->title ?? '' }}
                                                            @if(optional(optional($row->studentEnroll)->semester)->title)
                                                                · {{ $row->studentEnroll->semester->title }}
                                                            @endif
                                                        </span>
                                                    </small>
                                                </td>
                                                <td>{{ $row->category->title ?? '—' }}</td>
                                                <td>{{ $fmt($due) }} {!! $currency !!}</td>
                                                <td class="text-success">{{ $fmt($paid) }} {!! $currency !!}</td>
                                                <td class="{{ $bal > 0 ? 'text-danger' : 'text-muted' }}">
                                                    {{ $fmt($bal) }} {!! $currency !!}
                                                </td>
                                                <td>{{ $row->assign_date ? date($df, strtotime($row->assign_date)) : '' }}</td>
                                                <td>{{ $row->due_date ? date($df, strtotime($row->due_date)) : '' }}</td>
                                                <td>
                                                    @switch($row->status)
                                                        @case(0) <span class="badge badge-warning">{{ __('unpaid') }}</span> @break
                                                        @case(1) <span class="badge badge-success">{{ __('fully_paid') }}</span> @break
                                                        @case(2) <span class="badge badge-info">{{ __('partially_paid') }}</span> @break
                                                        @case(3) <span class="badge badge-secondary">{{ __('cancelled') }}</span> @break
                                                        @default <span class="badge badge-light">{{ $row->status }}</span>
                                                    @endswitch
                                                    @if($row->payment_plan_id)
                                                        <br><small class="badge badge-warning mt-1">{{ __('payment_plan') }}</small>
                                                    @endif
                                                </td>
                                                <td><span class="badge {{ $row->origin_badge }}">{{ $origins[$row->origin] ?? $row->origin }}</span></td>
                                                @canany(['fee-assignments-history-delete', 'fee-assignments-history-transfer', 'fee-assignments-history-edit'])
                                                @php
                                                    $deletable = $paid <= 0
                                                        && !in_array((int) $row->status, [1, 2], true)
                                                        && !$row->resitRequest
                                                        && !($row->payment_plan_id && optional($row->paymentPlan)->status === 'active');
                                                    $transferable = $paid > 0
                                                        && !in_array((int) $row->status, [3], true)
                                                        && !$row->resitRequest;
                                                @endphp
                                                <td class="text-center text-nowrap">
                                                    @can('fee-assignments-history-edit')
                                                        {{-- Only where the amount came from a configured source. A
                                                             resit fee is set by the resit flow, and a transferred-in
                                                             row is a payment record rather than a charge of its own. --}}
                                                        @if(in_array($row->origin, ['program_semester_fee', 'fees_master', 'manual', 'auto_progression'], true))
                                                            <button type="button" class="btn btn-sm btn-primary btn-correct-amount"
                                                                    data-fee-id="{{ $row->id }}"
                                                                    title="{{ __('Correct the amount') }}">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                            {{-- Its own button, not a second field on the amount one:
                                                                 a fee whose amount is frozen can still have the wrong
                                                                 date on it, and the two refuse for different reasons. --}}
                                                            <button type="button" class="btn btn-sm btn-info btn-correct-due-date"
                                                                    data-fee-id="{{ $row->id }}"
                                                                    title="{{ __('Correct the payment deadline') }}">
                                                                <i class="fas fa-calendar-alt"></i>
                                                            </button>
                                                        @endif
                                                    @endcan
                                                    @can('fee-assignments-history-transfer')
                                                        @if($transferable)
                                                            <button type="button" class="btn btn-sm btn-success btn-transfer"
                                                                    data-fee-id="{{ $row->id }}"
                                                                    data-fee-paid="{{ $paid }}"
                                                                    data-fee-label="{{ ($row->category->title ?? '—').' — '.$name }}"
                                                                    title="{{ __('transfer_payment') }}">
                                                                <i class="fas fa-exchange-alt"></i>
                                                            </button>
                                                        @endif
                                                    @endcan
                                                    @can('fee-assignments-history-delete')
                                                        @if($deletable)
                                                            <form action="{{ route($route.'.destroy', $row->id) }}" method="POST"
                                                                  onsubmit="return confirm('{{ __('confirm_delete_fee_assignment') }}');"
                                                                  style="display:inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger"
                                                                        title="{{ __('delete') }}">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @if(!$deletable && !$transferable)
                                                        <span class="text-muted" title="{{ __('locked_due_to_payments_or_links') }}">
                                                            <i class="fas fa-lock"></i>
                                                        </span>
                                                    @endif
                                                </td>
                                                @endcanany
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        @endif

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@can('fee-assignments-history-transfer')
{{-- ============== TRANSFER MODAL ============== --}}
<div class="modal fade" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="transferForm" method="POST" action="">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="transferModalLabel">
                        <i class="fas fa-exchange-alt"></i> {{ __('transfer_payment') }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ __('from') }}:</strong> <span id="transferSourceLabel">—</span><br>
                        <small>{{ __('paid') }}: <strong><span id="transferSourcePaid">0</span> {!! $currency !!}</strong></small>
                    </div>

                    <div class="form-group">
                        <label for="transfer_target">{{ __('to') }} <span class="text-danger">*</span></label>
                        <select class="form-control" name="target_fee_id" id="transfer_target" required>
                            <option value="">{{ __('loading') }}...</option>
                        </select>
                        <small class="form-text text-muted">{{ __('only_same_student_unpaid_fees_shown') }}</small>
                    </div>

                    <div class="form-group">
                        <label for="transfer_amount">{{ __('amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0.01" class="form-control"
                                   name="amount" id="transfer_amount" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="transferMaxBtn">{{ __('max') }}</button>
                            </div>
                        </div>
                        <small class="form-text text-muted" id="transferAmountHint">—</small>
                    </div>

                    <div class="form-group">
                        <label for="transfer_reason">{{ __('reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" id="transfer_reason" rows="2" required
                                  placeholder="{{ __('e_g_wrong_category_assigned') }}" minlength="3" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="submit" class="btn btn-success" id="transferSubmitBtn">
                        <i class="fas fa-exchange-alt"></i> {{ __('transfer') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var routeTargets = @json(route($route.'.transfer-targets', ['id' => '__ID__']));
    var routeTransfer = @json(route($route.'.transfer', ['id' => '__ID__']));
    var currency = @json($currency);
    var $modal   = $('#transferModal');
    var $select  = $('#transfer_target');
    var $amount  = $('#transfer_amount');
    var $hint    = $('#transferAmountHint');
    var $form    = $('#transferForm');
    var $maxBtn  = $('#transferMaxBtn');
    var sourcePaid = 0;
    var targetBalances = {};

    function fmt(n) { return Number(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    function recalcMax() {
        var balance = parseFloat(targetBalances[$select.val()] || 0);
        var max = Math.min(sourcePaid, balance);
        $amount.attr('max', max);
        $hint.text('Max transferable: ' + fmt(max) + ' ' + currency.replace(/<[^>]*>/g, ''));
        if (parseFloat($amount.val() || 0) > max) {
            $amount.val(max.toFixed(2));
        }
    }

    $(document).on('click', '.btn-transfer', function () {
        var feeId = $(this).data('fee-id');
        sourcePaid = parseFloat($(this).data('fee-paid') || 0);
        $('#transferSourceLabel').text($(this).data('fee-label'));
        $('#transferSourcePaid').text(fmt(sourcePaid));
        $form.attr('action', routeTransfer.replace('__ID__', feeId));
        $select.html('<option value="">Loading...</option>').prop('disabled', true);
        $amount.val('');
        $('#transfer_reason').val('');
        targetBalances = {};

        $.get(routeTargets.replace('__ID__', feeId)).done(function (resp) {
            var opts = '<option value="">— Select target fee —</option>';
            (resp.targets || []).forEach(function (t) {
                targetBalances[t.id] = t.balance;
                opts += '<option value="' + t.id + '">' +
                    t.category + ' (' + (t.semester || t.session || '') + ') · ' +
                    'Balance: ' + fmt(t.balance) + '</option>';
            });
            if ((resp.targets || []).length === 0) {
                opts = '<option value="">No transferable target fees for this student</option>';
            }
            $select.html(opts).prop('disabled', false);
        }).fail(function () {
            $select.html('<option value="">Failed to load targets</option>');
        });

        $modal.modal('show');
    });

    $select.on('change', recalcMax);
    $amount.on('input', function () {
        var max = parseFloat($amount.attr('max') || 0);
        if (parseFloat($amount.val() || 0) > max) {
            $amount.val(max.toFixed(2));
        }
    });
    $maxBtn.on('click', function () {
        var max = parseFloat($amount.attr('max') || 0);
        if (max > 0) $amount.val(max.toFixed(2));
    });

    $form.on('submit', function (e) {
        if (!$select.val()) {
            e.preventDefault();
            alert('Please select a target fee.');
            return false;
        }
        if (parseFloat($amount.val() || 0) <= 0) {
            e.preventDefault();
            alert('Please enter a valid amount.');
            return false;
        }
        $('#transferSubmitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Transferring...');
    });
})();
</script>
@endpush
@endcan

@can('fee-assignments-history-edit')
{{-- Correcting an amount.

     The figures are read back before anything is written, and the confirm
     button stays dead until the tick box is ticked - the same shape every other
     screen in this system that moves money now uses. --}}
<div class="modal fade" id="correctAmountModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" id="correct-amount-form">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">{{ __('Correct the fee amount') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="correct-amount-blockers"></div>

          <table class="table table-sm">
            <tbody>
              <tr><th class="fw-normal text-muted w-50">{{ __('field_name') }}</th><td class="fw-bold" id="ca-student">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_matricule') }}</th><td id="ca-matricule">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_fees_type') }}</th><td id="ca-category">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('Current amount') }}</th><td class="fw-bold" id="ca-current">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_already_paid') }}</th><td id="ca-paid">&mdash;</td></tr>
            </tbody>
          </table>

          <div class="form-group">
            <label for="ca-amount">{{ __('New amount') }} <span>*</span></label>
            <input type="number" step="0.01" min="0.01" class="form-control" name="fee_amount" id="ca-amount" required>
            <small class="form-text text-muted" id="ca-hint"></small>
          </div>

          <div class="form-group">
            <label for="ca-reason">{{ __('Why') }} <span>*</span></label>
            <input type="text" class="form-control" name="reason" id="ca-reason" minlength="3" maxlength="500"
                   placeholder="{{ __('e.g. wrong amount on the programme fee structure') }}" required>
            <small class="form-text text-muted">{{ __('Written onto the fee, so the correction can be traced later.') }}</small>
          </div>

          <div class="alert alert-warning mb-0" id="ca-effect"></div>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="ca-confirm">
            <label class="form-check-label" for="ca-confirm">
              {{ __('I have checked this against the programme fee structure and it is the correct amount.') }}
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
          <button type="submit" class="btn btn-primary" id="ca-submit" disabled>
            <i class="fas fa-check"></i> {{ __('Change the amount') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
{{-- Correcting a payment deadline.

     Quieter than changing an amount - nothing already recorded moves - but it
     decides the late fee the student will be charged when they next pay. That
     figure is worked out by the server, on the date being picked, and shown
     before anything is written. --}}
<div class="modal fade" id="correctDueDateModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" id="correct-due-date-form">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">{{ __('Correct the payment deadline') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="correct-due-date-blockers"></div>

          <table class="table table-sm">
            <tbody>
              <tr><th class="fw-normal text-muted w-50">{{ __('field_name') }}</th><td class="fw-bold" id="cd-student">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_matricule') }}</th><td id="cd-matricule">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_fees_type') }}</th><td id="cd-category">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_amount') }}</th><td id="cd-amount">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_already_paid') }}</th><td id="cd-paid">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('field_assign_date') }}</th><td id="cd-assigned">&mdash;</td></tr>
              <tr><th class="fw-normal text-muted">{{ __('Current deadline') }}</th><td class="fw-bold" id="cd-current">&mdash;</td></tr>
            </tbody>
          </table>

          <div class="form-group">
            <label for="cd-date">{{ __('New deadline') }} <span>*</span></label>
            <input type="date" class="form-control" name="due_date" id="cd-date" required>
            <small class="form-text text-muted" id="cd-hint"></small>
          </div>

          <div class="form-group">
            <label for="cd-reason">{{ __('Why') }} <span>*</span></label>
            <input type="text" class="form-control" name="reason" id="cd-reason" minlength="3" maxlength="500"
                   placeholder="{{ __('e.g. the deadline was never set on this fee') }}" required>
            <small class="form-text text-muted">{{ __('Written onto the fee, so the correction can be traced later.') }}</small>
          </div>

          <div class="alert alert-warning mb-0" id="cd-effect"></div>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="cd-confirm">
            <label class="form-check-label" for="cd-confirm">
              {{ __('I have checked this date against what the student was told, and it is correct.') }}
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
          <button type="submit" class="btn btn-primary" id="cd-submit" disabled>
            <i class="fas fa-check"></i> {{ __('Change the deadline') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function ($) {
  "use strict";

  var modalEl = document.getElementById('correctAmountModal');
  if (!modalEl) { return; }

  var current = 0;
  var paid = 0;

  function money(value) {
    return (parseFloat(value) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function describeEffect() {
    var next = parseFloat($('#ca-amount').val());
    var $effect = $('#ca-effect');

    if (isNaN(next) || next <= 0) {
      $effect.removeClass('alert-danger alert-info').addClass('alert-warning')
             .text({!! json_encode(__('Enter the amount this fee should be.')) !!});
      return;
    }

    if (next > current) {
      // Said in money rather than left for the bursar to work out: this is the
      // number the student will be asked for.
      $effect.removeClass('alert-danger alert-warning').addClass('alert-info')
             .text({!! json_encode(__('The charge goes up by')) !!} + ' ' + money(next - current) + '. '
                 + {!! json_encode(__('Outstanding after this:')) !!} + ' ' + money(Math.max(0, next - paid)) + '.');
    } else if (next < current && paid > 0.005) {
      $effect.removeClass('alert-info alert-warning').addClass('alert-danger')
             .text({!! json_encode(__('This fee cannot be lowered: the student has already paid against it.')) !!});
    } else if (next < current) {
      $effect.removeClass('alert-danger alert-info').addClass('alert-warning')
             .text({!! json_encode(__('The charge goes down. Allowed only because nothing has been paid against it.')) !!});
    } else {
      $effect.removeClass('alert-danger alert-info').addClass('alert-warning')
             .text({!! json_encode(__('That is already the amount on this fee.')) !!});
    }
  }

  $(document).on('click', '.btn-correct-amount', function () {
    var id = $(this).data('fee-id');

    // Cleared first, so a slow or failed load can never leave the previous
    // fee's figures on screen beside this one's id.
    $('#ca-student, #ca-matricule, #ca-category, #ca-current, #ca-paid').text('\u2014');
    $('#ca-amount, #ca-reason').val('');
    $('#ca-confirm').prop('checked', false);
    $('#ca-submit').prop('disabled', true);
    $('#correct-amount-blockers').empty();
    $('#ca-effect').removeClass('alert-danger alert-info').addClass('alert-warning').text('');
    $('#correct-amount-form').attr('action', '{{ url('admin/fee-assignments-history') }}/' + id + '/amount');

    $('#correctAmountModal').modal('show');

    $.get('{{ url('admin/fee-assignments-history') }}/' + id + '/amount', function (fee) {
      current = parseFloat(fee.fee_amount) || 0;
      paid = parseFloat(fee.paid_amount) || 0;

      $('#ca-student').text(fee.student || '\u2014');
      $('#ca-matricule').text(fee.matricule || '\u2014');
      $('#ca-category').text((fee.category || '\u2014') + ' \u2014 ' + (fee.programme || ''));
      $('#ca-current').text(money(current));
      $('#ca-paid').text(money(paid));
      $('#ca-amount').val(current.toFixed(2));
      $('#ca-hint').text(paid > 0.005
        ? {!! json_encode(__('Can only be raised: money has been paid against this fee.')) !!}
        : {!! json_encode(__('Nothing has been paid yet, so this can go either way.')) !!});

      if (fee.blockers && fee.blockers.length) {
        // Said before anything is typed, not after it is submitted.
        var list = fee.blockers.map(function (reason) {
          return '<li>' + $('<div>').text(reason).html() + '</li>';
        }).join('');
        $('#correct-amount-blockers').html(
          '<div class="alert alert-danger"><strong>' +
          {!! json_encode(__('This fee cannot be corrected here')) !!} +
          '</strong><ul class="mb-0 mt-1 ps-3">' + list + '</ul></div>');
        $('#ca-amount, #ca-reason, #ca-confirm').prop('disabled', true);
      } else {
        $('#ca-amount, #ca-reason, #ca-confirm').prop('disabled', false);
      }

      describeEffect();
    }).fail(function () {
      $('#correct-amount-blockers').html('<div class="alert alert-danger">' +
        {!! json_encode(__('Could not load this fee. Close and try again.')) !!} + '</div>');
    });
  });

  $('#ca-amount').on('input', describeEffect);

  $('#ca-confirm').on('change', function () {
    $('#ca-submit').prop('disabled', !this.checked);
  });

  $('#correctAmountModal').on('hidden.bs.modal', function () {
    $('#ca-confirm').prop('checked', false);
    $('#ca-submit').prop('disabled', true);
  });

  // ---------------------------------------------------------------------
  // The payment deadline.
  // ---------------------------------------------------------------------

  var dueCurrent = null;
  var dueAssigned = null;
  var dueFineNow = 0;
  var dueHasFines = false;
  var dueFeeId = null;
  var dueTimer = null;

  function dateText(value) {
    if (!value) { return {!! json_encode(__('Not set')) !!}; }
    var parts = String(value).split('-');
    var d = new Date(parts[0], (parts[1] || 1) - 1, parts[2] || 1);
    return isNaN(d.getTime()) ? value : d.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: 'numeric'});
  }

  // What the late fee would be is NOT worked out here. The browser asks the
  // server, which runs the one class that knows the rule, so the figure shown
  // is the figure the till will charge - a second copy of the arithmetic in
  // JavaScript is exactly how the two drift apart.
  function describeDueEffect() {
    var picked = $('#cd-date').val();
    var $effect = $('#cd-effect');

    if (!picked) {
      $effect.removeClass('alert-danger alert-info').addClass('alert-warning')
             .text({!! json_encode(__('Pick the date this fee should fall due.')) !!});
      return;
    }

    if (dueAssigned && picked < dueAssigned) {
      $effect.removeClass('alert-info alert-warning').addClass('alert-danger')
             .text({!! json_encode(__('That is before the fee was assigned on')) !!} + ' ' + dateText(dueAssigned) + '.');
      return;
    }

    $effect.removeClass('alert-danger alert-info').addClass('alert-warning')
           .text({!! json_encode(__('Working out the late fee...')) !!});

    clearTimeout(dueTimer);
    dueTimer = setTimeout(function () {
      $.get('{{ url('admin/fee-assignments-history') }}/' + dueFeeId + '/due-date', {due_date: picked}, function (fee) {
        // The modal may have moved on while this was in flight.
        if (String(fee.id) !== String(dueFeeId) || $('#cd-date').val() !== picked) { return; }

        var after = parseFloat(fee.fine_proposed) || 0;
        var before = parseFloat(fee.fine_now) || 0;
        var said = {!! json_encode(__('Deadline:')) !!} + ' ' + dateText(dueCurrent) + ' \u2192 ' + dateText(picked) + '.';

        if (Math.abs(after - before) < 0.005) {
          $effect.removeClass('alert-danger alert-warning').addClass('alert-info')
                 .text(said + ' ' + (after > 0.005
                   ? {!! json_encode(__('The late fee if they pay today is unchanged at')) !!} + ' ' + money(after) + '.'
                   : {!! json_encode(__('No late fee either way.')) !!}));
        } else if (after > before) {
          $effect.removeClass('alert-info alert-warning').addClass('alert-danger')
                 .text(said + ' ' + {!! json_encode(__('The late fee if they pay today rises from')) !!} + ' '
                     + money(before) + ' ' + {!! json_encode(__('to')) !!} + ' ' + money(after) + '.');
        } else {
          $effect.removeClass('alert-danger alert-warning').addClass('alert-info')
                 .text(said + ' ' + {!! json_encode(__('The late fee if they pay today drops from')) !!} + ' '
                     + money(before) + ' ' + {!! json_encode(__('to')) !!} + ' ' + money(after) + '.');
        }
      }).fail(function () {
        $effect.removeClass('alert-info alert-warning').addClass('alert-danger')
               .text({!! json_encode(__('Could not work out the late fee. Close and try again.')) !!});
      });
    }, 350);
  }

  $(document).on('click', '.btn-correct-due-date', function () {
    var id = $(this).data('fee-id');
    dueFeeId = id;

    $('#cd-student, #cd-matricule, #cd-category, #cd-amount, #cd-paid, #cd-assigned, #cd-current').text('\u2014');
    $('#cd-date, #cd-reason').val('');
    $('#cd-confirm').prop('checked', false);
    $('#cd-submit').prop('disabled', true);
    $('#correct-due-date-blockers').empty();
    $('#cd-effect').removeClass('alert-danger alert-info').addClass('alert-warning').text('');
    $('#correct-due-date-form').attr('action', '{{ url('admin/fee-assignments-history') }}/' + id + '/due-date');

    $('#correctDueDateModal').modal('show');

    $.get('{{ url('admin/fee-assignments-history') }}/' + id + '/due-date', function (fee) {
      dueCurrent = fee.due_date;
      dueAssigned = fee.assign_date;
      dueFineNow = parseFloat(fee.fine_now) || 0;
      dueHasFines = !!fee.has_fines;

      $('#cd-student').text(fee.student || '\u2014');
      $('#cd-matricule').text(fee.matricule || '\u2014');
      $('#cd-category').text((fee.category || '\u2014') + ' \u2014 ' + (fee.programme || ''));
      $('#cd-amount').text(money(fee.fee_amount));
      $('#cd-paid').text(money(fee.paid_amount));
      $('#cd-assigned').text(dateText(fee.assign_date));
      $('#cd-current').text(dateText(fee.due_date));
      $('#cd-date').val(fee.due_date || '');
      if (fee.assign_date) { $('#cd-date').attr('min', fee.assign_date); }

      $('#cd-hint').text(dueHasFines
        ? {!! json_encode(__('This fee type charges a late fee, so the date decides what they pay.')) !!}
        : {!! json_encode(__('This fee type charges no late fee. The date decides the reminders and the overdue lists.')) !!});

      if (fee.blockers && fee.blockers.length) {
        var list = fee.blockers.map(function (reason) {
          return '<li>' + $('<div>').text(reason).html() + '</li>';
        }).join('');
        $('#correct-due-date-blockers').html(
          '<div class="alert alert-danger"><strong>' +
          {!! json_encode(__('This deadline cannot be changed here')) !!} +
          '</strong><ul class="mb-0 mt-1 ps-3">' + list + '</ul></div>');
        $('#cd-date, #cd-reason, #cd-confirm').prop('disabled', true);
      } else {
        $('#cd-date, #cd-reason, #cd-confirm').prop('disabled', false);
      }

      describeDueEffect();
    }).fail(function () {
      $('#correct-due-date-blockers').html('<div class="alert alert-danger">' +
        {!! json_encode(__('Could not load this fee. Close and try again.')) !!} + '</div>');
    });
  });

  $('#cd-date').on('change input', describeDueEffect);

  $('#cd-confirm').on('change', function () {
    $('#cd-submit').prop('disabled', !this.checked);
  });

  $('#correctDueDateModal').on('hidden.bs.modal', function () {
    clearTimeout(dueTimer);
    $('#cd-confirm').prop('checked', false);
    $('#cd-submit').prop('disabled', true);
  });
})(jQuery);
</script>
@endpush
@endcan

@endsection
