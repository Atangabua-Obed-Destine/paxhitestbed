    <!-- Edit modal content -->
    <div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
              <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ __('modal_edit') }} {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Form Start -->
                    <div class="form-group">
                        <label for="title" class="form-label">{{ __('field_tax_title') }} <span>*</span></label>
                        <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" placeholder="e.g., Personal Income Tax, PAYE" required>

                        <div class="invalid-feedback">
                        {{ __('required_field') }} {{ __('field_tax_title') }} 
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tax_group_id_{{ $row->id }}" class="form-label">{{ __('field_tax_group') }}</label>
                        <select class="form-control" name="tax_group_id" id="tax_group_id_{{ $row->id }}">
                            <option value="">{{ __('standalone_bracket') }}</option>
                            @foreach($tax_groups as $group)
                            <option value="{{ $group->id }}" {{ $row->tax_group_id == $group->id ? 'selected' : '' }}>
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
                            <input class="form-check-input edit-is-dependent" type="checkbox" name="is_dependent" id="edit_is_dependent_{{ $row->id }}" value="1" {{ $row->is_dependent ? 'checked' : '' }} data-row-id="{{ $row->id }}">
                            <label class="form-check-label" for="edit_is_dependent_{{ $row->id }}">
                                <i class="fas fa-link text-warning"></i> {{ __('field_is_dependent') }}
                            </label>
                        </div>
                        <small class="form-text text-muted">{{ __('dependent_tax_help') }}</small>
                    </div>

                    <div id="edit_dependency_section_{{ $row->id }}" style="display: {{ $row->is_dependent ? 'block' : 'none' }};">
                        <div class="alert alert-warning py-2 px-3 mb-2">
                            <small><i class="fas fa-info-circle"></i> {{ __('dependent_tax_info') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_depends_on_type_{{ $row->id }}" class="form-label">{{ __('field_depends_on_type') }} <span>*</span></label>
                            <select class="form-control edit-depends-on-type" name="depends_on_type" id="edit_depends_on_type_{{ $row->id }}" data-row-id="{{ $row->id }}">
                                <option value="">{{ __('select') }}</option>
                                <option value="tax_group" {{ $row->depends_on_type == 'tax_group' ? 'selected' : '' }}>{{ __('depends_on_tax_group') }}</option>
                                <option value="tax_setting" {{ $row->depends_on_type == 'tax_setting' ? 'selected' : '' }}>{{ __('depends_on_tax_setting') }}</option>
                            </select>
                        </div>

                        <div class="form-group" id="edit_depends_on_group_section_{{ $row->id }}" style="display: {{ $row->depends_on_type == 'tax_group' ? 'block' : 'none' }};">
                            <label for="edit_depends_on_group_id_{{ $row->id }}" class="form-label">{{ __('field_source_tax_group') }} <span>*</span></label>
                            <select class="form-control edit-depends-on-group" name="depends_on_id" id="edit_depends_on_group_id_{{ $row->id }}" {{ $row->depends_on_type == 'tax_group' ? '' : 'disabled' }}>
                                <option value="">{{ __('select') }}</option>
                                @foreach($tax_groups as $group)
                                <option value="{{ $group->id }}" {{ $row->depends_on_id == $group->id && $row->depends_on_type == 'tax_group' ? 'selected' : '' }}>
                                    {{ $group->title }} ({{ $group->code ?? 'N/A' }})
                                </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">{{ __('source_tax_group_help') }}</small>
                        </div>

                        <div class="form-group" id="edit_depends_on_tax_section_{{ $row->id }}" style="display: {{ $row->depends_on_type == 'tax_setting' ? 'block' : 'none' }};">
                            <label for="edit_depends_on_tax_id_{{ $row->id }}" class="form-label">{{ __('field_source_tax_setting') }} <span>*</span></label>
                            <select class="form-control edit-depends-on-tax" name="depends_on_id" id="edit_depends_on_tax_id_{{ $row->id }}" {{ $row->depends_on_type == 'tax_setting' ? '' : 'disabled' }}>
                                <option value="">{{ __('select') }}</option>
                                @foreach($standalone_taxes as $stax)
                                <option value="{{ $stax->id }}" {{ $row->depends_on_id == $stax->id && $row->depends_on_type == 'tax_setting' ? 'selected' : '' }}>
                                    {{ $stax->title }}
                                </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">{{ __('source_tax_setting_help') }}</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_tax_type_{{ $row->id }}" class="form-label">{{ __('field_tax_type') }} <span>*</span></label>
                        <select class="form-control" name="tax_type" id="edit_tax_type_{{ $row->id }}" required>
                            <option value="">{{ __('select') }}</option>
                            <option value="1" {{ $row->tax_type == 1 ? 'selected' : '' }}>{{ __('tax_type_percentage') }}</option>
                            <option value="2" {{ $row->tax_type == 2 ? 'selected' : '' }}>{{ __('tax_type_fixed') }}</option>
                        </select>

                        <div class="invalid-feedback">
                        {{ __('required_field') }} {{ __('field_tax_type') }} 
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_paid_by_{{ $row->id }}" class="form-label">{{ __('field_paid_by') }} <span>*</span></label>
                        <select class="form-control" name="paid_by" id="edit_paid_by_{{ $row->id }}" required>
                            <option value="employee" {{ $row->paid_by == 'employee' ? 'selected' : '' }}>{{ __('paid_by_employee') }}</option>
                            <option value="employer" {{ $row->paid_by == 'employer' ? 'selected' : '' }}>{{ __('paid_by_employer') }}</option>
                            <option value="both" {{ $row->paid_by == 'both' ? 'selected' : '' }}>{{ __('paid_by_both') }}</option>
                        </select>
                        <small class="form-text text-muted">{{ __('paid_by_help') }}</small>
                    </div>

                    {{--
                        Which body this tax is owed to. The account is the
                        authority: 431 for the social bodies, 443 for the
                        State. The tax remittance screen groups by it, so a tax
                        pointed at the wrong one is declared to the wrong
                        office.
                    --}}
                    <div class="form-group">
                        <label for="edit_liability_account_{{ $row->id }}" class="form-label">{{ __('Owed to') }}</label>
                        <select class="form-control" name="liability_account_id" id="edit_liability_account_{{ $row->id }}">
                            <option value="">{{ __('Use the default payroll tax account') }}</option>
                            @foreach(\App\Models\ChartOfAccount::where('class_number', 4)->where('account_category', 'detail')->where('is_active', 1)->orderBy('account_code')->get() as $account)
                                <option value="{{ $account->id }}" {{ $row->liability_account_id == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_code }} — {{ $account->account_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            {{ __('The liability account this accrues to, and the authority it is declared to on the tax remittance screen.') }}
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="min_amount" class="form-label">{{ __('field_min_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                        <input type="text" class="form-control" name="min_amount" id="min_amount" value="{{ round($row->min_amount, 2) }}" required>

                        <div class="invalid-feedback">
                        {{ __('required_field') }} {{ __('field_min_amount') }} 
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="max_amount" class="form-label">{{ __('field_max_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                        <input type="text" class="form-control" name="max_amount" id="max_amount" value="{{ round($row->max_amount, 2) }}" required>

                        <div class="invalid-feedback">
                        {{ __('required_field') }} {{ __('field_max_amount') }}
                        </div>
                    </div>

                    <!-- Employee Contribution Fields -->
                    <div id="edit_employee_contribution_section_{{ $row->id }}" style="display: {{ $row->paid_by != 'employer' ? 'block' : 'none' }};">
                        <div class="form-group" id="edit_percentage_field_{{ $row->id }}" style="display: {{ $row->tax_type == 1 ? 'block' : 'none' }};">
                            <label for="edit_percentange_{{ $row->id }}" class="form-label">{{ __('field_employee_percentage') }} (%) <span>*</span></label>
                            <input type="text" class="form-control" name="percentange" id="edit_percentange_{{ $row->id }}" value="{{ round($row->percentange, 2) }}">

                            <div class="invalid-feedback">
                            {{ __('required_field') }} {{ __('field_percentage') }}
                            </div>
                        </div>

                        <div class="form-group" id="edit_fixed_amount_field_{{ $row->id }}" style="display: {{ $row->tax_type == 2 ? 'block' : 'none' }};">
                            <label for="edit_fixed_amount_{{ $row->id }}" class="form-label">{{ __('field_employee_fixed_amount') }} ({!! $setting->currency_symbol !!}) <span>*</span></label>
                            <input type="text" class="form-control" name="fixed_amount" id="edit_fixed_amount_{{ $row->id }}" value="{{ round($row->fixed_amount, 2) }}">

                            <div class="invalid-feedback">
                            {{ __('required_field') }} {{ __('field_fixed_amount') }}
                            </div>
                        </div>
                    </div>

                    <!-- Employer Contribution Fields -->
                    <div id="edit_employer_contribution_section_{{ $row->id }}" style="display: {{ $row->paid_by == 'both' || $row->paid_by == 'employer' ? 'block' : 'none' }};">
                        <hr>
                        <h6 class="text-primary"><i class="fas fa-building"></i> {{ __('employer_contribution') }}</h6>
                        
                        <div class="form-group" id="edit_employer_percentage_field_{{ $row->id }}" style="display: {{ $row->tax_type == 1 ? 'block' : 'none' }};">
                            <label for="edit_employer_percentage_{{ $row->id }}" class="form-label">{{ __('field_employer_percentage') }} (%)</label>
                            <input type="text" class="form-control" name="employer_percentage" id="edit_employer_percentage_{{ $row->id }}" value="{{ round($row->employer_percentage, 2) }}">
                        </div>

                        <div class="form-group" id="edit_employer_fixed_amount_field_{{ $row->id }}" style="display: {{ $row->tax_type == 2 ? 'block' : 'none' }};">
                            <label for="edit_employer_fixed_amount_{{ $row->id }}" class="form-label">{{ __('field_employer_fixed_amount') }} ({!! $setting->currency_symbol !!})</label>
                            <input type="text" class="form-control" name="employer_fixed_amount" id="edit_employer_fixed_amount_{{ $row->id }}" value="{{ round($row->employer_fixed_amount, 2) }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="max_no_taxable_amount" class="form-label">{{ __('field_max_no_taxable_amount') }} ({!! $setting->currency_symbol !!})</label>
                        <input type="text" class="form-control" name="max_no_taxable_amount" id="max_no_taxable_amount" value="{{ round($row->max_no_taxable_amount, 2) }}">

                        <div class="invalid-feedback">
                        {{ __('field_max_no_taxable_amount') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bracket_order_{{ $row->id }}" class="form-label">{{ __('field_bracket_order') }}</label>
                        <input type="number" class="form-control" name="bracket_order" id="bracket_order_{{ $row->id }}" value="{{ $row->bracket_order ?? 0 }}" min="0">
                        <small class="form-text text-muted">{{ __('bracket_order_help') }}</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="effective_from_{{ $row->id }}" class="form-label">{{ __('field_effective_from') }}</label>
                                <input type="date" class="form-control" name="effective_from" id="effective_from_{{ $row->id }}" value="{{ $row->effective_from ? $row->effective_from->format('Y-m-d') : '' }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="effective_to_{{ $row->id }}" class="form-label">{{ __('field_effective_to') }}</label>
                                <input type="date" class="form-control" name="effective_to" id="effective_to_{{ $row->id }}" value="{{ $row->effective_to ? $row->effective_to->format('Y-m-d') : '' }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label">{{ __('select_status') }}</label>
                        <select class="form-control" name="status" id="status">
                            <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                            <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                        </select>
                    </div>
                    <!-- Form End -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                </div>

              </form>
            </div>
        </div>
    </div>