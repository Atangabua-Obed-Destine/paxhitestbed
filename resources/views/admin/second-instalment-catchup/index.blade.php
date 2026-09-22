@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@php
    $money = fn ($amount) => number_format((float) $amount, 0);
    $canApply = auth()->user()->can('second-instalment-catchup-apply');
    $billable = $rows->whereNull('blocked');
    $blocked = $rows->whereNotNull('blocked');
@endphp

<div class="main-body">
    <div class="page-wrapper">

        <div class="row">
            <div class="col-sm-12">
                <div class="alert alert-info">
                    <strong>{{ __('What this page is for') }}</strong><br>
                    {{ __('In 2025/2026 the instalments were configured one semester at a time, so students who left during the first semester were never given a Second Instalment and their overpayment has nowhere to go. This raises the missing fee at the programme\'s configured amount and lets each student\'s credit settle what it can.') }}
                    <br><strong>{{ __('This bills students. Read the list before applying, and untick anyone who should not be billed.') }}</strong>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <form method="get" action="{{ route('admin.second-instalment-catchup.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="session_id">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session_id" id="session_id">
                                        @foreach ($sessions as $session)
                                            <option value="{{ $session->id }}" @selected($selected_session == $session->id)>{{ $session->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-5">
                                    <label for="program_id">{{ __('field_program') }}</label>
                                    <select class="form-control select2" name="program_id" id="program_id">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach ($programs as $program)
                                            <option value="{{ $program->id }}" @selected($selected_program == $program->id)>{{ $program->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Who would be billed --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">
                            {{ __(':count student(s) have no :category for this year', ['count' => $rows->count(), 'category' => $category->title ?? __('Second Instalment')]) }}
                        </h5>
                        @if ($canApply)
                            <button type="button" class="btn btn-danger btn-sm" id="btn-apply" @disabled($billable->isEmpty())>
                                <i class="fas fa-file-invoice-dollar me-1"></i>{{ __('Raise the ticked fees') }}
                            </button>
                        @endif
                    </div>
                    <div class="card-block">
                        @if ($rows->isEmpty())
                            <p class="text-success mb-0"><i class="fas fa-check me-1"></i>{{ __('Every student for this year already has a Second Instalment.') }}</p>
                        @else
                            <p class="text-muted">
                                {{ __('Totals for the ticked students are worked out as you tick them. The fee is attached to the student\'s latest regular enrolment for the year, which for someone who left is their first semester.') }}
                            </p>

                            <div class="row mb-3">
                                <div class="col-md-3"><strong id="sum-students">{{ $totals['students'] }}</strong> {{ __('students') }}</div>
                                <div class="col-md-3"><strong id="sum-raise">{{ $money($totals['raise']) }}</strong> {{ __('raised (FCFA)') }}</div>
                                <div class="col-md-3"><strong id="sum-credit">{{ $money($totals['credit']) }}</strong> {{ __('settled by credit') }}</div>
                                <div class="col-md-3"><strong id="sum-owing">{{ $money($totals['owing']) }}</strong> {{ __('left owing') }}</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 3%">
                                                @if ($canApply)
                                                    <input type="checkbox" id="tick-all" checked>
                                                @endif
                                            </th>
                                            <th>{{ __('field_matricule') }}</th>
                                            <th>{{ __('field_name') }}</th>
                                            <th>{{ __('field_program') }}</th>
                                            <th>{{ __('Reached second semester') }}</th>
                                            <th class="text-end">{{ __('Fee') }}</th>
                                            <th class="text-end">{{ __('Credit held') }}</th>
                                            <th class="text-end">{{ __('Left owing') }}</th>
                                            <th>{{ __('field_due_date') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr class="{{ $row['blocked'] ? 'text-muted' : '' }}">
                                                <td>
                                                    @if ($canApply && !$row['blocked'])
                                                        <input type="checkbox" class="tick-student" value="{{ $row['student_id'] }}"
                                                               data-raise="{{ $row['amount'] + $row['fine'] }}"
                                                               data-credit="{{ $row['credit'] }}"
                                                               data-owing="{{ $row['owing'] }}" checked>
                                                    @endif
                                                </td>
                                                <td>{{ $row['matricule'] }}</td>
                                                <td>{{ strtoupper($row['name']) }}</td>
                                                <td>{{ $row['program'] }}</td>
                                                <td>
                                                    @if ($row['reached_second_semester'])
                                                        <span class="badge bg-secondary">{{ __('yes') }}</span>
                                                    @else
                                                        <span class="badge bg-warning text-dark">{{ __('no — left in the first semester') }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ $row['blocked'] ? '—' : $money($row['amount'] + $row['fine']) }}</td>
                                                <td class="text-end">{{ $money($row['credit']) }}</td>
                                                <td class="text-end fw-bold">{{ $row['blocked'] ? '—' : $money($row['owing']) }}</td>
                                                <td>{{ $row['due_date'] }}</td>
                                            </tr>
                                            @if ($row['blocked'])
                                                <tr class="text-muted">
                                                    <td></td>
                                                    <td colspan="8"><small><i class="fas fa-exclamation-triangle me-1"></i>{{ $row['blocked'] }}</small></td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($blocked->isNotEmpty())
                                <div class="alert alert-warning mb-0">
                                    {{ __(':count student(s) cannot be billed until their programme has a Second Instalment configured on Program Semester Fee.', ['count' => $blocked->count()]) }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- What this tool has already raised --}}
        @if ($raised->isNotEmpty())
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">{{ __('Already raised by this page') }}</h5>
                        @if ($canApply)
                            <button type="button" class="btn btn-outline-danger btn-sm" id="btn-undo" @disabled($raised->where('removable', true)->isEmpty())>
                                <i class="fas fa-undo me-1"></i>{{ __('Take back the untouched ones') }}
                            </button>
                        @endif
                    </div>
                    <div class="card-block table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 3%"></th>
                                    <th>{{ __('field_matricule') }}</th>
                                    <th>{{ __('field_name') }}</th>
                                    <th>{{ __('field_program') }}</th>
                                    <th class="text-end">{{ __('Fee') }}</th>
                                    <th class="text-end">{{ __('Settled by credit') }}</th>
                                    <th class="text-end">{{ __('Paid') }}</th>
                                    <th>{{ __('field_status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($raised as $fee)
                                    <tr>
                                        <td>
                                            @if ($canApply && $fee['removable'])
                                                <input type="checkbox" class="tick-fee" value="{{ $fee['fee_id'] }}">
                                            @endif
                                        </td>
                                        <td>{{ $fee['matricule'] }}</td>
                                        <td>{{ strtoupper($fee['name']) }}</td>
                                        <td>{{ $fee['program'] }}</td>
                                        <td class="text-end">{{ $money($fee['amount']) }}</td>
                                        <td class="text-end">{{ $money($fee['credit_applied']) }}</td>
                                        <td class="text-end">{{ $money($fee['paid']) }}</td>
                                        <td>
                                            @if ($fee['removable'])
                                                <span class="badge bg-secondary">{{ __('untouched — can be taken back') }}</span>
                                            @else
                                                <span class="badge bg-success">{{ __('money against it — kept') }}</span>
                                            @endif
                                        </td>
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
</div>
@endsection

@if ($canApply)
@push('scripts')
{{-- SweetAlert2 is not part of the admin layout; each page that uses it loads it. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    function escapeHtml(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
    function money(value) { return Math.round(value).toLocaleString(); }

    var ticked = function () { return $('.tick-student:checked'); };

    function retotal() {
        var raise = 0, credit = 0, owing = 0;

        ticked().each(function () {
            raise += parseFloat(this.dataset.raise) || 0;
            credit += parseFloat(this.dataset.credit) || 0;
            owing += parseFloat(this.dataset.owing) || 0;
        });

        $('#sum-students').text(ticked().length);
        $('#sum-raise').text(money(raise));
        $('#sum-credit').text(money(credit));
        $('#sum-owing').text(money(owing));
        $('#btn-apply').prop('disabled', ticked().length === 0);

        return { raise: raise, credit: credit, owing: owing, count: ticked().length };
    }

    $(document).on('change', '.tick-student', retotal);
    $('#tick-all').on('change', function () {
        $('.tick-student').prop('checked', this.checked);
        retotal();
    });

    $('#btn-apply').on('click', function () {
        var button = this;
        var totals = retotal();

        if (!totals.count) { return; }

        Swal.fire({
            title: @json(__('Raise these fees?')),
            html: escapeHtml(totals.count + ' ' + @json(__('student(s) will be billed'))) + ' <strong>' + money(totals.raise) + ' FCFA</strong>.'
                + '<br>' + escapeHtml(@json(__('Credit settles'))) + ' <strong>' + money(totals.credit) + ' FCFA</strong>, '
                + escapeHtml(@json(__('leaving'))) + ' <strong>' + money(totals.owing) + ' FCFA</strong> ' + escapeHtml(@json(__('owed by students.')))
                + '<br><br><strong>' + escapeHtml(@json(__('Take a database backup first.'))) + '</strong>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: @json(__('Raise the fees')),
            cancelButtonText: @json(__('cancel'))
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            var original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + escapeHtml(@json(__('Working…')));

            $.ajax({
                url: @json(route('admin.second-instalment-catchup.apply')),
                type: 'POST',
                data: {
                    _token: @json(csrf_token()),
                    session_id: @json($selected_session),
                    students: ticked().map(function () { return this.value; }).get()
                },
                success: function (res) {
                    var html = '<p>' + escapeHtml(res.message) + '</p>';
                    var failed = (res.result && res.result.failed) || [];

                    if (failed.length) {
                        html += '<div class="text-start small"><strong>' + escapeHtml(@json(__('Not raised:'))) + '</strong><ul class="mb-0">'
                            + failed.map(function (f) { return '<li>' + escapeHtml(f.student + ' — ' + f.reason) + '</li>'; }).join('')
                            + '</ul></div>';
                    }

                    Swal.fire({ icon: failed.length ? 'warning' : 'success', title: @json(__('Done')), html: html })
                        .then(function () { location.reload(); });
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: @json(__('error')),
                        text: (xhr.responseJSON && xhr.responseJSON.message) || @json(__('an_error_occurred'))
                    });
                    button.innerHTML = original;
                    button.disabled = false;
                }
            });
        });
    });

    $('#btn-undo').on('click', function () {
        var button = this;
        var fees = $('.tick-fee:checked').map(function () { return this.value; }).get();

        if (!fees.length) {
            Swal.fire({ icon: 'info', title: @json(__('Nothing chosen')), text: @json(__('Tick the fees to take back first.')) });
            return;
        }

        Swal.fire({
            title: @json(__('Take these fees back?')),
            html: escapeHtml(fees.length + ' ' + @json(__('fee(s) will be removed. Only fees with no money against them can go.'))),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: @json(__('Take them back')),
            cancelButtonText: @json(__('cancel'))
        }).then(function (result) {
            if (!result.isConfirmed) { return; }

            button.disabled = true;

            $.ajax({
                url: @json(route('admin.second-instalment-catchup.undo')),
                type: 'POST',
                data: { _token: @json(csrf_token()), fees: fees },
                success: function (res) {
                    Swal.fire({ icon: 'success', title: @json(__('Done')), text: res.message })
                        .then(function () { location.reload(); });
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: @json(__('error')),
                        text: (xhr.responseJSON && xhr.responseJSON.message) || @json(__('an_error_occurred'))
                    });
                    button.disabled = false;
                }
            });
        });
    });
})();
</script>
@endpush
@endif
