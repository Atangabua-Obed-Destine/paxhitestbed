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
                                            @canany(['fee-assignments-history-delete', 'fee-assignments-history-transfer'])
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
                                                @canany(['fee-assignments-history-delete', 'fee-assignments-history-transfer'])
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
@endsection
