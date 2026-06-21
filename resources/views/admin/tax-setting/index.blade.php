@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            @can($access.'-create')
            <div class="col-md-4">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_create') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <!-- Form Start -->
                            <div class="form-group">
                                <label for="title" class="form-label">{{ __('field_tax_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" placeholder="e.g., Personal Income Tax, PAYE, Social Security" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_tax_title') }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tax_group_id" class="form-label">{{ __('field_tax_group') }}</label>
                                <select class="form-control" name="tax_group_id" id="tax_group_id">
                                    <option value="">{{ __('standalone_bracket') }}</option>
                                    @foreach($tax_groups as $group)
                                    <option value="{{ $group->id }}" {{ old('tax_group_id') == $group->id ? 'selected' : '' }}>
                                        {{ $group->title }} 
                                        @if($group->is_progressive)
                                            ({{ __('progressive_tax') }})
                                        @else
                                            ({{ __('flat_rate_tax') }})
                                        @endif
                                    </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">{{ __('tax_group_help') }}</small>
                            </div>

                            <!-- Dependent Tax Configuration -->
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_dependent" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_dependent" id="is_dependent" value="1" {{ old('is_dependent') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_dependent">
                                        <i class="fas fa-link text-warning"></i> {{ __('field_is_dependent') }}
                                    </label>
                                </div>
                                <small class="form-text text-muted">{{ __('dependent_tax_help') }}</small>
                            </div>

                            <div id="dependency_section" style="display: {{ old('is_dependent') ? 'block' : 'none' }};">
                                <div class="alert alert-warning py-2 px-3 mb-2">
                                    <small><i class="fas fa-info-circle"></i> {{ __('dependent_tax_info') }}</small>
                                </div>
                                <div class="form-group">
                                    <label for="depends_on_type" class="form-label">{{ __('field_depends_on_type') }} <span>*</span></label>
                                    <select class="form-control" name="depends_on_type" id="depends_on_type">
                                        <option value="">{{ __('select') }}</option>
                                        <option value="tax_group" {{ old('depends_on_type') == 'tax_group' ? 'selected' : '' }}>{{ __('depends_on_tax_group') }}</option>
                                        <option value="tax_setting" {{ old('depends_on_type') == 'tax_setting' ? 'selected' : '' }}>{{ __('depends_on_tax_setting') }}</option>
                                    </select>
                                </div>

                                <div class="form-group" id="depends_on_group_section" style="display: {{ old('depends_on_type') == 'tax_group' ? 'block' : 'none' }};">
                                    <label for="depends_on_group_id" class="form-label">{{ __('field_source_tax_group') }} <span>*</span></label>
                                    <select class="form-control" name="depends_on_id" id="depends_on_group_id" {{ old('depends_on_type') == 'tax_group' ? '' : 'disabled' }}>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($tax_groups as $group)
                                        <option value="{{ $group->id }}" {{ old('depends_on_id') == $group->id && old('depends_on_type') == 'tax_group' ? 'selected' : '' }}>
                                            {{ $group->title }} ({{ $group->code ?? 'N/A' }})
                                        </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">{{ __('source_tax_group_help') }}</small>
                                </div>

                                <div class="form-group" id="depends_on_tax_section" style="display: {{ old('depends_on_type') == 'tax_setting' ? 'block' : 'none' }};">
                                    <label for="depends_on_tax_id" class="form-label">{{ __('field_source_tax_setting') }} <span>*</span></label>
                                    <select class="form-control" name="depends_on_id" id="depends_on_tax_id" {{ old('depends_on_type') == 'tax_setting' ? '' : 'disabled' }}>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($standalone_taxes as $stax)
                                        <option value="{{ $stax->id }}" {{ old('depends_on_id') == $stax->id && old('depends_on_type') == 'tax_setting' ? 'selected' : '' }}>
                                            {{ $stax->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">{{ __('source_tax_setting_help') }}</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tax_type" class="form-label">{{ __('field_tax_type') }} <span>*</span></label>
                                <select class="form-control" name="tax_type" id="tax_type" required>
                                    <option value="">{{ __('select') }}</option>
                                    <option value="1" {{ old('tax_type') == 1 ? 'selected' : '' }}>{{ __('tax_type_percentage') }}</option>
                                    <option value="2" {{ old('tax_type') == 2 ? 'selected' : '' }}>{{ __('tax_type_fixed') }}</option>
                                </select>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_tax_type') }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="paid_by" class="form-label">{{ __('field_paid_by') }} <span>*</span></label>
                                <select class="form-control" name="paid_by" id="paid_by" required>
                                    <option value="employee" {{ old('paid_by', 'employee') == 'employee' ? 'selected' : '' }}>{{ __('paid_by_employee') }}</option>
                                    <option value="employer" {{ old('paid_by') == 'employer' ? 'selected' : '' }}>{{ __('paid_by_employer') }}</option>
                                    <option value="both" {{ old('paid_by') == 'both' ? 'selected' : '' }}>{{ __('paid_by_both') }}</option>
                                </select>
                                <small class="form-text text-muted">{{ __('paid_by_help') }}</small>
                            </div>

                            <div class="form-group">
                                <label for="min_amount" class="form-label">{{ __('field_min_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                                <input type="text" class="form-control" name="min_amount" id="min_amount" value="{{ old('min_amount') }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_min_amount') }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="max_amount" class="form-label">{{ __('field_max_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                                <input type="text" class="form-control" name="max_amount" id="max_amount" value="{{ old('max_amount') }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_max_amount') }}
                                </div>
                            </div>

                            <!-- Employee Contribution Fields -->
                            <div id="employee_contribution_section">
                                <div class="form-group" id="percentage_field">
                                    <label for="percentange" class="form-label">{{ __('field_employee_percentage') }} (%) <span>*</span></label>
                                    <input type="text" class="form-control" name="percentange" id="percentange" value="{{ old('percentange') }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_percentage') }}
                                    </div>
                                </div>

                                <div class="form-group" id="fixed_amount_field" style="display: none;">
                                    <label for="fixed_amount" class="form-label">{{ __('field_employee_fixed_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                                    <input type="text" class="form-control" name="fixed_amount" id="fixed_amount" value="{{ old('fixed_amount') }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_fixed_amount') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Employer Contribution Fields -->
                            <div id="employer_contribution_section" style="display: none;">
                                <hr>
                                <h6 class="text-primary"><i class="fas fa-building"></i> {{ __('employer_contribution') }}</h6>
                                
                                <div class="form-group" id="employer_percentage_field">
                                    <label for="employer_percentage" class="form-label">{{ __('field_employer_percentage') }} (%)</label>
                                    <input type="text" class="form-control" name="employer_percentage" id="employer_percentage" value="{{ old('employer_percentage') }}">
                                </div>

                                <div class="form-group" id="employer_fixed_amount_field" style="display: none;">
                                    <label for="employer_fixed_amount" class="form-label">{{ __('field_employer_fixed_amount') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="text" class="form-control" name="employer_fixed_amount" id="employer_fixed_amount" value="{{ old('employer_fixed_amount') }}">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="max_no_taxable_amount" class="form-label">{{ __('field_max_no_taxable_amount') }} ({!! $setting->currency_symbol !!})</label>
                                <input type="text" class="form-control" name="max_no_taxable_amount" id="max_no_taxable_amount" value="{{ old('max_no_taxable_amount') }}">

                                <div class="invalid-feedback">
                                {{ __('field_max_no_taxable_amount') }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="bracket_order" class="form-label">{{ __('field_bracket_order') }}</label>
                                <input type="number" class="form-control" name="bracket_order" id="bracket_order" value="{{ old('bracket_order') }}" min="0" placeholder="{{ __('auto_assign') }}">
                                <small class="form-text text-muted">{{ __('bracket_order_help') }}</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_from" class="form-label">{{ __('field_effective_from') }}</label>
                                        <input type="date" class="form-control" name="effective_from" id="effective_from" value="{{ old('effective_from') }}">
                                        <small class="form-text text-muted">{{ __('effective_from_help') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_to" class="form-label">{{ __('field_effective_to') }}</label>
                                        <input type="date" class="form-control" name="effective_to" id="effective_to" value="{{ old('effective_to') }}">
                                        <small class="form-text text-muted">{{ __('effective_to_help') }}</small>
                                    </div>
                                </div>
                            </div>
                            <!-- Form End -->
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_order') }}</th>
                                        <th>{{ __('field_tax_title') }}</th>
                                        <th>{{ __('field_tax_group') }}</th>
                                        <th>{{ __('field_tax_type') }}</th>
                                        <th>{{ __('field_min_amount') }}</th>
                                        <th>{{ __('field_max_amount') }}</th>
                                        <th>{{ __('field_tax_value') }}</th>
                                        <th>{{ __('field_effective_dates') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td><span class="badge badge-secondary">{{ $row->bracket_order ?? 0 }}</span></td>
                                        <td><strong>{{ $row->title ?? 'N/A' }}</strong></td>
                                        <td>
                                            @if($row->taxGroup)
                                                <a href="{{ route('admin.tax-group.show', $row->taxGroup->id) }}" class="badge badge-primary">
                                                    {{ $row->taxGroup->title }}
                                                </a>
                                                @if($row->taxGroup->is_progressive)
                                                    <br><small class="text-success"><i class="fas fa-layer-group"></i> {{ __('progressive') }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">{{ __('standalone') }}</span>
                                            @endif
                                            @if($row->is_dependent)
                                                <br><span class="badge badge-warning"><i class="fas fa-link"></i> {{ __('dependent') }}</span>
                                                @if($row->dependency_label)
                                                    <br><small class="text-warning">{{ __('calculated_from') }}: {{ $row->dependency_label }}</small>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->tax_type == 1)
                                            <span class="badge badge-info">{{ __('tax_type_percentage') }}</span>
                                            @else
                                            <span class="badge badge-warning">{{ __('tax_type_fixed') }}</span>
                                            @endif
                                            <br>
                                            @if($row->paid_by == 'both')
                                            <span class="badge badge-success"><i class="fas fa-handshake"></i> {{ __('shared') }}</span>
                                            @elseif($row->paid_by == 'employer')
                                            <span class="badge badge-primary"><i class="fas fa-building"></i> {{ __('employer') }}</span>
                                            @else
                                            <span class="badge badge-secondary"><i class="fas fa-user"></i> {{ __('employee') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                          @if(isset($setting->decimal_place))
                                          {{ number_format((float)$row->min_amount, $setting->decimal_place, '.', '') }} 
                                          @else
                                          {{ number_format((float)$row->min_amount, 2, '.', '') }} 
                                          @endif
                                          {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                          @if(isset($setting->decimal_place))
                                          {{ number_format((float)$row->max_amount, $setting->decimal_place, '.', '') }} 
                                          @else
                                          {{ number_format((float)$row->max_amount, 2, '.', '') }} 
                                          @endif
                                          {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                          @if($row->tax_type == 1)
                                            {{-- Percentage type --}}
                                            @if($row->paid_by != 'employer')
                                            <small class="text-muted">{{ __('employee') }}:</small> {{ number_format((float)$row->percentange, 2, '.', '') }}%<br>
                                            @endif
                                            @if($row->paid_by == 'employer' || $row->paid_by == 'both')
                                            <small class="text-muted">{{ __('employer') }}:</small> {{ number_format((float)$row->employer_percentage, 2, '.', '') }}%
                                            @endif
                                          @else
                                            {{-- Fixed amount type --}}
                                            @if($row->paid_by != 'employer')
                                            <small class="text-muted">{{ __('employee') }}:</small> {{ number_format((float)$row->fixed_amount, 2, '.', '') }} {!! $setting->currency_symbol !!}<br>
                                            @endif
                                            @if($row->paid_by == 'employer' || $row->paid_by == 'both')
                                            <small class="text-muted">{{ __('employer') }}:</small> {{ number_format((float)$row->employer_fixed_amount, 2, '.', '') }} {!! $setting->currency_symbol !!}
                                            @endif
                                          @endif
                                        </td>
                                        <td>
                                            @if($row->effective_from || $row->effective_to)
                                                <small>
                                                    @if($row->effective_from)
                                                        {{ \Carbon\Carbon::parse($row->effective_from)->format('d M Y') }}
                                                    @else
                                                        {{ __('always') }}
                                                    @endif
                                                    -
                                                    @if($row->effective_to)
                                                        {{ \Carbon\Carbon::parse($row->effective_to)->format('d M Y') }}
                                                    @else
                                                        {{ __('forever') }}
                                                    @endif
                                                </small>
                                                @php
                                                    $isEffective = $row->isEffective();
                                                @endphp
                                                @if(!$isEffective)
                                                    <br><span class="badge badge-warning">{{ __('not_effective') }}</span>
                                                @endif
                                            @else
                                                <span class="text-muted">{{ __('always_effective') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if( $row->status == 1 )
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-icon btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#exemptionsModal-{{ $row->id }}" title="{{ __('manage_exemptions') }}">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                            
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}">
                                                <i class="far fa-edit"></i>
                                            </button>
                                            <!-- Include Edit modal -->
                                            @include($view.'.edit')
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <!-- Include Delete modal -->
                                            @include('admin.layouts.inc.delete')
                                            @endcan
                                            
                                            <!-- Include Exemptions modal -->
                                            @include($view.'.exemptions')
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
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
    
    // Toggle tax type fields on create form
    function updateCreateFormFields() {
        var type = $('#tax_type').val();
        var paidBy = $('#paid_by').val();
        
        // Toggle percentage vs fixed amount fields
        if(type == '1') {
            $('#percentage_field').show();
            $('#percentange').attr('required', true);
            $('#fixed_amount_field').hide();
            $('#fixed_amount').removeAttr('required');
            $('#employer_percentage_field').show();
            $('#employer_fixed_amount_field').hide();
        } else if(type == '2') {
            $('#percentage_field').hide();
            $('#percentange').removeAttr('required');
            $('#fixed_amount_field').show();
            $('#fixed_amount').attr('required', true);
            $('#employer_percentage_field').hide();
            $('#employer_fixed_amount_field').show();
        } else {
            $('#percentage_field').hide();
            $('#fixed_amount_field').hide();
            $('#percentange').removeAttr('required');
            $('#fixed_amount').removeAttr('required');
        }
        
        // Toggle employer contribution section based on paid_by
        if(paidBy == 'both') {
            $('#employer_contribution_section').show();
            $('#employee_contribution_section').show();
        } else if(paidBy == 'employer') {
            $('#employer_contribution_section').show();
            $('#employee_contribution_section').hide();
            $('#percentange').removeAttr('required');
            $('#fixed_amount').removeAttr('required');
        } else {
            $('#employer_contribution_section').hide();
            $('#employee_contribution_section').show();
        }
    }
    
    $('#tax_type').on('change', updateCreateFormFields);
    $('#paid_by').on('change', updateCreateFormFields);
    
    // Initialize on page load
    updateCreateFormFields();

    // Toggle tax type fields on edit modals
    $('[id^="edit_tax_type_"]').on('change', function() {
        var id = $(this).attr('id').replace('edit_tax_type_', '');
        var type = $(this).val();
        
        if(type == '1') {
            $('#edit_percentage_field_' + id).show();
            $('#edit_percentange_' + id).attr('required', true);
            $('#edit_fixed_amount_field_' + id).hide();
            $('#edit_fixed_amount_' + id).removeAttr('required');
            $('#edit_employer_percentage_field_' + id).show();
            $('#edit_employer_fixed_amount_field_' + id).hide();
        } else if(type == '2') {
            $('#edit_percentage_field_' + id).hide();
            $('#edit_percentange_' + id).removeAttr('required');
            $('#edit_fixed_amount_field_' + id).show();
            $('#edit_fixed_amount_' + id).attr('required', true);
            $('#edit_employer_percentage_field_' + id).hide();
            $('#edit_employer_fixed_amount_field_' + id).show();
        }
    });
    
    // Toggle paid_by on edit modals
    $('[id^="edit_paid_by_"]').on('change', function() {
        var id = $(this).attr('id').replace('edit_paid_by_', '');
        var paidBy = $(this).val();
        
        if(paidBy == 'both') {
            $('#edit_employer_contribution_section_' + id).show();
            $('#edit_employee_contribution_section_' + id).show();
        } else if(paidBy == 'employer') {
            $('#edit_employer_contribution_section_' + id).show();
            $('#edit_employee_contribution_section_' + id).hide();
        } else {
            $('#edit_employer_contribution_section_' + id).hide();
            $('#edit_employee_contribution_section_' + id).show();
        }
    });

    // ===== Dependency Toggle – Create Form =====
    function updateDependencyFields() {
        var isChecked = $('#is_dependent').is(':checked');
        if (isChecked) {
            $('#dependency_section').slideDown(200);
        } else {
            $('#dependency_section').slideUp(200);
        }
    }

    function updateDependencyTypeFields() {
        var depType = $('#depends_on_type').val();
        if (depType === 'tax_group') {
            $('#depends_on_group_section').show();
            $('#depends_on_group_id').prop('disabled', false);
            $('#depends_on_tax_section').hide();
            $('#depends_on_tax_id').prop('disabled', true);
        } else if (depType === 'tax_setting') {
            $('#depends_on_group_section').hide();
            $('#depends_on_group_id').prop('disabled', true);
            $('#depends_on_tax_section').show();
            $('#depends_on_tax_id').prop('disabled', false);
        } else {
            $('#depends_on_group_section').hide();
            $('#depends_on_group_id').prop('disabled', true);
            $('#depends_on_tax_section').hide();
            $('#depends_on_tax_id').prop('disabled', true);
        }
    }

    $('#is_dependent').on('change', updateDependencyFields);
    $('#depends_on_type').on('change', updateDependencyTypeFields);

    // Initialize dependency on load
    updateDependencyFields();
    updateDependencyTypeFields();

    // ===== Dependency Toggle – Edit Modals =====
    function updateEditDependencyFields(rowId) {
        var isChecked = $('#edit_is_dependent_' + rowId).is(':checked');
        if (isChecked) {
            $('#edit_dependency_section_' + rowId).slideDown(200);
        } else {
            $('#edit_dependency_section_' + rowId).slideUp(200);
        }
    }

    function updateEditDependencyTypeFields(rowId) {
        var depType = $('#edit_depends_on_type_' + rowId).val();
        if (depType === 'tax_group') {
            $('#edit_depends_on_group_section_' + rowId).show();
            $('#edit_depends_on_group_id_' + rowId).prop('disabled', false);
            $('#edit_depends_on_tax_section_' + rowId).hide();
            $('#edit_depends_on_tax_id_' + rowId).prop('disabled', true);
        } else if (depType === 'tax_setting') {
            $('#edit_depends_on_group_section_' + rowId).hide();
            $('#edit_depends_on_group_id_' + rowId).prop('disabled', true);
            $('#edit_depends_on_tax_section_' + rowId).show();
            $('#edit_depends_on_tax_id_' + rowId).prop('disabled', false);
        } else {
            $('#edit_depends_on_group_section_' + rowId).hide();
            $('#edit_depends_on_group_id_' + rowId).prop('disabled', true);
            $('#edit_depends_on_tax_section_' + rowId).hide();
            $('#edit_depends_on_tax_id_' + rowId).prop('disabled', true);
        }
    }

    $('.edit-is-dependent').on('change', function() {
        var rowId = $(this).data('row-id');
        updateEditDependencyFields(rowId);
    });

    $('.edit-depends-on-type').on('change', function() {
        var rowId = $(this).data('row-id');
        updateEditDependencyTypeFields(rowId);
    });
</script>
@endsection