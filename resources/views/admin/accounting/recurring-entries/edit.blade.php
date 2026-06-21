@extends('admin.layouts.master')

@section('title', __('Edit Recurring Entry'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Edit Recurring Entry') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.recurring-entries.index') }}">{{ __('Recurring Entries') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Edit') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <form action="{{ route('admin.recurring-entries.update', $recurringEntry) }}" method="POST" id="recurringEntryForm">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Basic Information -->
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Entry Information') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" name="name" value="{{ old('name', $recurringEntry->name) }}" required>
                                            @error('name')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name_fr">{{ __('Name (French)') }}</label>
                                            <input type="text" class="form-control @error('name_fr') is-invalid @enderror" 
                                                   id="name_fr" name="name_fr" value="{{ old('name_fr', $recurringEntry->name_fr) }}">
                                            @error('name_fr')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">{{ __('Description') }}</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="2">{{ old('description', $recurringEntry->description) }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Journal Entry Lines -->
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Journal Entry Lines') }}</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-success btn-sm" onclick="addLine()">
                                        <i class="fas fa-plus"></i> {{ __('Add Line') }}
                                    </button>
                                </div>
                            </div>
                            <div class="card-body table-responsive p-0">
                                <table class="table" id="linesTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 35%;">{{ __('Account') }}</th>
                                            <th style="width: 20%;">{{ __('Debit') }}</th>
                                            <th style="width: 20%;">{{ __('Credit') }}</th>
                                            <th style="width: 20%;">{{ __('Description') }}</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="linesBody">
                                        @foreach($recurringEntry->lines as $index => $line)
                                        <tr class="line-row">
                                            <td>
                                                <select class="form-control select2 account-select" name="lines[{{ $index }}][account_id]" required>
                                                    <option value="">{{ __('Select Account') }}</option>
                                                    @foreach($accounts as $account)
                                                        <option value="{{ $account->id }}" {{ $line->account_id == $account->id ? 'selected' : '' }}>
                                                            {{ $account->code }} - {{ $account->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control debit-input" 
                                                       name="lines[{{ $index }}][debit]" step="0.01" min="0"
                                                       value="{{ $line->debit > 0 ? $line->debit : '' }}"
                                                       onchange="updateTotals()">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control credit-input" 
                                                       name="lines[{{ $index }}][credit]" step="0.01" min="0"
                                                       value="{{ $line->credit > 0 ? $line->credit : '' }}"
                                                       onchange="updateTotals()">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" 
                                                       name="lines[{{ $index }}][description]" 
                                                       value="{{ $line->description }}">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="removeLine(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-light font-weight-bold">
                                        <tr>
                                            <td>{{ __('Total') }}</td>
                                            <td id="totalDebit" class="text-right">0</td>
                                            <td id="totalCredit" class="text-right">0</td>
                                            <td colspan="2">
                                                <span id="balanceMessage" class="text-success"></span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Schedule Settings -->
                        <div class="card card-outline card-warning">
                            <div class="card-header">
                                <h3 class="card-title">{{ __('Schedule Settings') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="frequency">{{ __('Frequency') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('frequency') is-invalid @enderror" 
                                            id="frequency" name="frequency" required>
                                        @foreach($frequencies as $key => $label)
                                            <option value="{{ $key }}" {{ old('frequency', $recurringEntry->frequency) == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('frequency')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group" id="dayOfMonthGroup">
                                    <label for="day_of_month">{{ __('Day of Month') }}</label>
                                    <select class="form-control" id="day_of_month" name="day_of_month">
                                        <option value="">{{ __('Last Day') }}</option>
                                        @for($i = 1; $i <= 28; $i++)
                                            <option value="{{ $i }}" {{ old('day_of_month', $recurringEntry->day_of_month) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <div class="form-group" id="dayOfWeekGroup" style="display: none;">
                                    <label for="day_of_week">{{ __('Day of Week') }}</label>
                                    <select class="form-control" id="day_of_week" name="day_of_week">
                                        <option value="1" {{ old('day_of_week', $recurringEntry->day_of_week) == 1 ? 'selected' : '' }}>{{ __('Monday') }}</option>
                                        <option value="2" {{ old('day_of_week', $recurringEntry->day_of_week) == 2 ? 'selected' : '' }}>{{ __('Tuesday') }}</option>
                                        <option value="3" {{ old('day_of_week', $recurringEntry->day_of_week) == 3 ? 'selected' : '' }}>{{ __('Wednesday') }}</option>
                                        <option value="4" {{ old('day_of_week', $recurringEntry->day_of_week) == 4 ? 'selected' : '' }}>{{ __('Thursday') }}</option>
                                        <option value="5" {{ old('day_of_week', $recurringEntry->day_of_week) == 5 ? 'selected' : '' }}>{{ __('Friday') }}</option>
                                        <option value="6" {{ old('day_of_week', $recurringEntry->day_of_week) == 6 ? 'selected' : '' }}>{{ __('Saturday') }}</option>
                                        <option value="0" {{ old('day_of_week', $recurringEntry->day_of_week) == 0 ? 'selected' : '' }}>{{ __('Sunday') }}</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="next_run_date">{{ __('Next Run Date') }}</label>
                                    <input type="date" class="form-control @error('next_run_date') is-invalid @enderror" 
                                           id="next_run_date" name="next_run_date" 
                                           value="{{ old('next_run_date', $recurringEntry->next_run_date?->format('Y-m-d')) }}">
                                    @error('next_run_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="end_date">{{ __('End Date') }}</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                           id="end_date" name="end_date" 
                                           value="{{ old('end_date', $recurringEntry->end_date?->format('Y-m-d')) }}">
                                    @error('end_date')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">{{ __('Leave empty for no end date') }}</small>
                                </div>

                                <div class="form-group">
                                    <label for="max_occurrences">{{ __('Max Occurrences') }}</label>
                                    <input type="number" class="form-control @error('max_occurrences') is-invalid @enderror" 
                                           id="max_occurrences" name="max_occurrences" min="1"
                                           value="{{ old('max_occurrences', $recurringEntry->max_occurrences) }}">
                                    @error('max_occurrences')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">{{ __('Leave empty for unlimited') }}</small>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="auto_post" name="auto_post" value="1"
                                               {{ old('auto_post', $recurringEntry->auto_post) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="auto_post">{{ __('Auto Post Entries') }}</label>
                                    </div>
                                    <small class="form-text text-muted">{{ __('Automatically post entries when processed') }}</small>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> {{ __('Update Entry') }}
                                </button>
                                <a href="{{ route('admin.recurring-entries.show', $recurringEntry) }}" class="btn btn-secondary btn-block">
                                    <i class="fas fa-times"></i> {{ __('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
let lineIndex = {{ count($recurringEntry->lines) }};
const accounts = @json($accounts);

$(function() {
    initSelect2();
    updateTotals();
    toggleDayFields();
    
    $('#frequency').change(toggleDayFields);
});

function initSelect2() {
    $('.select2').select2({
        theme: 'bootstrap4',
        allowClear: true,
        width: '100%'
    });
}

function toggleDayFields() {
    const frequency = $('#frequency').val();
    if (frequency === 'weekly' || frequency === 'biweekly') {
        $('#dayOfWeekGroup').show();
        $('#dayOfMonthGroup').hide();
    } else if (frequency === 'monthly' || frequency === 'quarterly' || frequency === 'semiannually' || frequency === 'annually') {
        $('#dayOfMonthGroup').show();
        $('#dayOfWeekGroup').hide();
    } else {
        $('#dayOfMonthGroup').hide();
        $('#dayOfWeekGroup').hide();
    }
}

function addLine() {
    const row = `
        <tr class="line-row">
            <td>
                <select class="form-control select2 account-select" name="lines[${lineIndex}][account_id]" required>
                    <option value="">{{ __('Select Account') }}</option>
                    ${accounts.map(a => `<option value="${a.id}">${a.code} - ${a.name}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" class="form-control debit-input" name="lines[${lineIndex}][debit]" step="0.01" min="0" onchange="updateTotals()">
            </td>
            <td>
                <input type="number" class="form-control credit-input" name="lines[${lineIndex}][credit]" step="0.01" min="0" onchange="updateTotals()">
            </td>
            <td>
                <input type="text" class="form-control" name="lines[${lineIndex}][description]">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeLine(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#linesBody').append(row);
    lineIndex++;
    initSelect2();
}

function removeLine(btn) {
    if ($('.line-row').length > 2) {
        $(btn).closest('tr').remove();
        updateTotals();
    } else {
        alert('{{ __('Minimum 2 lines required') }}');
    }
}

function updateTotals() {
    let totalDebit = 0;
    let totalCredit = 0;
    
    $('.debit-input').each(function() {
        totalDebit += parseFloat($(this).val()) || 0;
    });
    
    $('.credit-input').each(function() {
        totalCredit += parseFloat($(this).val()) || 0;
    });
    
    $('#totalDebit').text(totalDebit.toLocaleString());
    $('#totalCredit').text(totalCredit.toLocaleString());
    
    if (totalDebit === totalCredit && totalDebit > 0) {
        $('#balanceMessage').removeClass('text-danger').addClass('text-success').text('{{ __('Balanced') }}');
    } else if (totalDebit > 0 || totalCredit > 0) {
        $('#balanceMessage').removeClass('text-success').addClass('text-danger').text('{{ __('Not Balanced') }}');
    } else {
        $('#balanceMessage').text('');
    }
}

$('#recurringEntryForm').submit(function(e) {
    let totalDebit = 0;
    let totalCredit = 0;
    
    $('.debit-input').each(function() {
        totalDebit += parseFloat($(this).val()) || 0;
    });
    
    $('.credit-input').each(function() {
        totalCredit += parseFloat($(this).val()) || 0;
    });
    
    if (totalDebit !== totalCredit) {
        e.preventDefault();
        alert('{{ __('Total debits must equal total credits') }}');
        return false;
    }
    
    if (totalDebit === 0) {
        e.preventDefault();
        alert('{{ __('Entry amount must be greater than zero') }}');
        return false;
    }
});
</script>
@endsection
