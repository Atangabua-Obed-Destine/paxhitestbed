@extends('admin.layouts.master')
@section('title', $row->title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Group Info -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $row->title }}</h5>
                        @if($row->code)
                        <span class="badge badge-secondary">{{ $row->code }}</span>
                        @endif
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary btn-sm mb-3">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                        @can($access.'-edit')
                        <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-info btn-sm mb-3">
                            <i class="far fa-edit"></i> {{ __('btn_edit') }}
                        </a>
                        @endcan

                        <table class="table table-bordered table-sm">
                            <tr>
                                <th>{{ __('field_type') }}</th>
                                <td>
                                    @if($row->is_progressive)
                                    <span class="badge badge-info">{{ __('progressive') }}</span>
                                    @else
                                    <span class="badge badge-warning">{{ __('flat_rate') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('field_status') }}</th>
                                <td>
                                    @if($row->status)
                                    <span class="badge badge-success">{{ __('status_active') }}</span>
                                    @else
                                    <span class="badge badge-danger">{{ __('status_inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('field_effective_from') }}</th>
                                <td>{{ $row->effective_from ? $row->effective_from->format('d M Y') : __('always') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('field_effective_to') }}</th>
                                <td>{{ $row->effective_to ? $row->effective_to->format('d M Y') : __('forever') }}</td>
                            </tr>
                        </table>

                        @if($row->description)
                        <div class="mt-3">
                            <strong>{{ __('field_description') }}:</strong>
                            <p class="text-muted">{{ $row->description }}</p>
                        </div>
                        @endif

                        @if($row->is_progressive)
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> {{ __('progressive_tax_note') }}
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Add Bracket Form -->
                @can($access.'-create')
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('add_bracket') }}</h5>
                    </div>
                    <div class="card-block">
                        <form action="{{ route($route.'.bracket.store', $row->id) }}" method="post" class="needs-validation" novalidate>
                            @csrf
                            
                            <div class="form-group">
                                <label for="title" class="form-label">{{ __('field_bracket_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title" placeholder="e.g., First 100,000" required>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="min_amount" class="form-label">{{ __('field_min_amount') }} <span>*</span></label>
                                        <input type="number" step="0.01" class="form-control" name="min_amount" id="min_amount" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="max_amount" class="form-label">{{ __('field_max_amount') }} <span>*</span></label>
                                        <input type="number" step="0.01" class="form-control" name="max_amount" id="max_amount" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tax_type" class="form-label">{{ __('field_tax_type') }} <span>*</span></label>
                                <select class="form-control" name="tax_type" id="add_tax_type" required>
                                    <option value="1">{{ __('tax_type_percentage') }}</option>
                                    <option value="2">{{ __('tax_type_fixed') }}</option>
                                </select>
                            </div>

                            <div class="form-group" id="add_percentage_field">
                                <label for="percentange" class="form-label">{{ __('field_percentage') }} (%) <span>*</span></label>
                                <input type="number" step="0.01" class="form-control" name="percentange" id="percentange" min="0" max="100">
                            </div>

                            <div class="form-group" id="add_fixed_field" style="display: none;">
                                <label for="fixed_amount" class="form-label">{{ __('field_fixed_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                                <input type="number" step="0.01" class="form-control" name="fixed_amount" id="fixed_amount" min="0">
                            </div>

                            <div class="form-group">
                                <label for="max_no_taxable_amount" class="form-label">{{ __('field_tax_free_allowance') }}</label>
                                <input type="number" step="0.01" class="form-control" name="max_no_taxable_amount" value="0" min="0">
                                <small class="text-muted">{{ __('tax_free_allowance_help') }}</small>
                            </div>

                            <div class="form-group">
                                <label for="bracket_order" class="form-label">{{ __('field_bracket_order') }}</label>
                                <input type="number" class="form-control" name="bracket_order" min="0" placeholder="{{ __('auto_assign') }}">
                            </div>

                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-plus"></i> {{ __('btn_add') }}
                            </button>
                        </form>
                    </div>
                </div>
                @endcan
            </div>

            <!-- Brackets List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('tax_brackets') }} ({{ $row->brackets->count() }})</h5>
                    </div>
                    <div class="card-block">
                        @if($row->brackets->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">{{ __('field_order') }}</th>
                                        <th>{{ __('field_bracket_title') }}</th>
                                        <th>{{ __('field_range') }}</th>
                                        <th>{{ __('field_rate') }}</th>
                                        <th>{{ __('field_tax_free') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th width="100">{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row->brackets as $bracket)
                                    <tr>
                                        <td><span class="badge badge-secondary">{{ $bracket->bracket_order }}</span></td>
                                        <td>{{ $bracket->title }}</td>
                                        <td>
                                            {{ number_format($bracket->min_amount, 2) }} - {{ number_format($bracket->max_amount, 2) }}
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if($bracket->tax_type == 1)
                                            <span class="badge badge-info">{{ number_format($bracket->percentange, 2) }}%</span>
                                            @else
                                            <span class="badge badge-warning">{{ number_format($bracket->fixed_amount, 2) }} {!! $setting->currency_symbol !!}</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($bracket->max_no_taxable_amount, 2) }}</td>
                                        <td>
                                            @if($bracket->status)
                                            <span class="badge badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editBracketModal-{{ $bracket->id }}">
                                                <i class="far fa-edit"></i>
                                            </button>
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteBracketModal-{{ $bracket->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>

                                    <!-- Edit Bracket Modal -->
                                    <div id="editBracketModal-{{ $bracket->id }}" class="modal fade" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <form action="{{ route($route.'.bracket.update', [$row->id, $bracket->id]) }}" method="post">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ __('modal_edit') }} {{ __('bracket') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label class="form-label">{{ __('field_bracket_title') }} <span>*</span></label>
                                                            <input type="text" class="form-control" name="title" value="{{ $bracket->title }}" required>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label class="form-label">{{ __('field_min_amount') }} <span>*</span></label>
                                                                    <input type="number" step="0.01" class="form-control" name="min_amount" value="{{ $bracket->min_amount }}" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label class="form-label">{{ __('field_max_amount') }} <span>*</span></label>
                                                                    <input type="number" step="0.01" class="form-control" name="max_amount" value="{{ $bracket->max_amount }}" required>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="form-group">
                                                            <label class="form-label">{{ __('field_tax_type') }} <span>*</span></label>
                                                            <select class="form-control edit-tax-type" name="tax_type" data-id="{{ $bracket->id }}" required>
                                                                <option value="1" {{ $bracket->tax_type == 1 ? 'selected' : '' }}>{{ __('tax_type_percentage') }}</option>
                                                                <option value="2" {{ $bracket->tax_type == 2 ? 'selected' : '' }}>{{ __('tax_type_fixed') }}</option>
                                                            </select>
                                                        </div>

                                                        <div class="form-group edit-percentage-field-{{ $bracket->id }}" style="display: {{ $bracket->tax_type == 1 ? 'block' : 'none' }};">
                                                            <label class="form-label">{{ __('field_percentage') }} (%)</label>
                                                            <input type="number" step="0.01" class="form-control" name="percentange" value="{{ $bracket->percentange }}" min="0" max="100">
                                                        </div>

                                                        <div class="form-group edit-fixed-field-{{ $bracket->id }}" style="display: {{ $bracket->tax_type == 2 ? 'block' : 'none' }};">
                                                            <label class="form-label">{{ __('field_fixed_amount') }} ({!! $setting->currency_symbol !!})</label>
                                                            <input type="number" step="0.01" class="form-control" name="fixed_amount" value="{{ $bracket->fixed_amount }}" min="0">
                                                        </div>

                                                        <div class="form-group">
                                                            <label class="form-label">{{ __('field_tax_free_allowance') }}</label>
                                                            <input type="number" step="0.01" class="form-control" name="max_no_taxable_amount" value="{{ $bracket->max_no_taxable_amount }}" min="0">
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label class="form-label">{{ __('field_bracket_order') }}</label>
                                                                    <input type="number" class="form-control" name="bracket_order" value="{{ $bracket->bracket_order }}" min="0">
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label class="form-label">{{ __('field_status') }}</label>
                                                                    <select class="form-control" name="status">
                                                                        <option value="1" {{ $bracket->status ? 'selected' : '' }}>{{ __('status_active') }}</option>
                                                                        <option value="0" {{ !$bracket->status ? 'selected' : '' }}>{{ __('status_inactive') }}</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                                                        <button type="submit" class="btn btn-success">{{ __('btn_update') }}</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Bracket Modal -->
                                    <div id="deleteBracketModal-{{ $bracket->id }}" class="modal fade" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ __('modal_delete') }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>{{ __('delete_confirm') }} <strong>{{ $bracket->title }}</strong>?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                                                    <form action="{{ route($route.'.bracket.destroy', [$row->id, $bracket->id]) }}" method="post" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">{{ __('btn_delete') }}</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Tax Calculation Example -->
                        <div class="card mt-4 bg-light">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-calculator"></i> {{ __('tax_calculation_example') }}</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">{{ __('field_salary') }}</span>
                                            <input type="number" class="form-control" id="example_salary" placeholder="Enter salary" step="0.01">
                                            <button class="btn btn-primary" type="button" id="calculate_tax_btn">
                                                <i class="fas fa-calculator"></i> {{ __('calculate') }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div id="tax_result" class="alert alert-success" style="display: none;"></div>
                                    </div>
                                </div>
                                <div id="tax_breakdown" class="mt-3" style="display: none;"></div>
                            </div>
                        </div>
                        @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> {{ __('no_brackets_yet') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";

// Toggle tax type fields on add form
$('#add_tax_type').on('change', function() {
    var type = $(this).val();
    if(type == '1') {
        $('#add_percentage_field').show();
        $('#add_fixed_field').hide();
    } else {
        $('#add_percentage_field').hide();
        $('#add_fixed_field').show();
    }
});

// Toggle tax type fields on edit modals
$('.edit-tax-type').on('change', function() {
    var id = $(this).data('id');
    var type = $(this).val();
    if(type == '1') {
        $('.edit-percentage-field-' + id).show();
        $('.edit-fixed-field-' + id).hide();
    } else {
        $('.edit-percentage-field-' + id).hide();
        $('.edit-fixed-field-' + id).show();
    }
});

// Tax calculation example
$('#calculate_tax_btn').on('click', function() {
    var salary = parseFloat($('#example_salary').val()) || 0;
    if(salary <= 0) {
        alert('Please enter a valid salary');
        return;
    }

    var brackets = @json($row->brackets);
    var isProgressive = {{ $row->is_progressive ? 'true' : 'false' }};
    var totalTax = 0;
    var breakdown = [];

    brackets.forEach(function(bracket) {
        if(salary < parseFloat(bracket.min_amount)) {
            return;
        }

        var taxableInBracket = 0;
        var bracketTax = 0;

        if(isProgressive) {
            // Progressive: tax only the portion within this bracket
            taxableInBracket = Math.min(salary, parseFloat(bracket.max_amount)) - parseFloat(bracket.min_amount);
            if(taxableInBracket <= 0) return;
            
            // Apply tax-free allowance
            taxableInBracket = Math.max(0, taxableInBracket - parseFloat(bracket.max_no_taxable_amount || 0));
        } else {
            // Flat: if salary in bracket, apply to full salary
            if(salary < parseFloat(bracket.min_amount) || salary > parseFloat(bracket.max_amount)) {
                return;
            }
            taxableInBracket = Math.max(0, salary - parseFloat(bracket.max_no_taxable_amount || 0));
        }

        if(bracket.tax_type == 2) {
            bracketTax = parseFloat(bracket.fixed_amount);
        } else {
            bracketTax = (taxableInBracket / 100) * parseFloat(bracket.percentange);
        }

        totalTax += bracketTax;
        breakdown.push({
            title: bracket.title,
            range: parseFloat(bracket.min_amount).toLocaleString() + ' - ' + parseFloat(bracket.max_amount).toLocaleString(),
            taxable: taxableInBracket,
            rate: bracket.tax_type == 1 ? bracket.percentange + '%' : bracket.fixed_amount + ' (fixed)',
            tax: bracketTax
        });
    });

    // Display result
    $('#tax_result').html('<strong>{{ __("total_tax") }}:</strong> ' + totalTax.toFixed(2) + ' {!! $setting->currency_symbol !!}').show();

    // Display breakdown
    var breakdownHtml = '<table class="table table-sm table-bordered"><thead><tr><th>{{ __("bracket") }}</th><th>{{ __("field_range") }}</th><th>{{ __("taxable_amount") }}</th><th>{{ __("field_rate") }}</th><th>{{ __("tax_amount") }}</th></tr></thead><tbody>';
    breakdown.forEach(function(b) {
        breakdownHtml += '<tr><td>' + b.title + '</td><td>' + b.range + '</td><td>' + b.taxable.toFixed(2) + '</td><td>' + b.rate + '</td><td>' + b.tax.toFixed(2) + '</td></tr>';
    });
    breakdownHtml += '</tbody></table>';
    $('#tax_breakdown').html(breakdownHtml).show();
});
</script>
@endsection
