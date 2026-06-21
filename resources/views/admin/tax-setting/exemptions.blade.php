    <!-- Exemptions modal content -->
    <div id="exemptionsModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ __('manage_exemptions') }} - {{ $row->title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Add Exemption Form -->
                    <form action="{{ route('admin.tax-exemptions.store') }}" method="post" class="mb-4">
                        @csrf
                        <input type="hidden" name="tax_setting_id" value="{{ $row->id }}">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_id_{{ $row->id }}" class="form-label">{{ __('select_staff') }} <span>*</span></label>
                                    <select class="form-control" name="user_id" id="user_id_{{ $row->id }}" required>
                                        <option value="">{{ __('select') }}</option>
                                        @php
                                        $staff_list = App\User::where('status', '1')
                                                            ->where('is_admin', '!=', 1)
                                                            ->whereNotIn('id', $row->exemptStaff->pluck('id')->toArray())
                                                            ->orderBy('staff_id', 'asc')
                                                            ->get();
                                        @endphp
                                        @foreach($staff_list as $staff)
                                        <option value="{{ $staff->id }}">{{ $staff->staff_id }} - {{ $staff->first_name }} {{ $staff->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reason_{{ $row->id }}" class="form-label">{{ __('field_reason') }}</label>
                                    <input type="text" class="form-control" name="reason" id="reason_{{ $row->id }}" placeholder="e.g., Diplomatic exemption, Contract terms">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Custom Tax Fields -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> <strong>Optional:</strong> Set a custom tax rate for this staff member. If left blank, they will be fully exempt from this tax.
                                </div>
                            </div>
                            
                            @if($row->tax_type == 1)
                            <!-- Percentage Tax Type -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="custom_percentage_{{ $row->id }}" class="form-label">{{ __('field_custom_percentage') }} (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="custom_percentage" id="custom_percentage_{{ $row->id }}" placeholder="e.g., 5.5">
                                    <small class="text-muted">Default: {{ number_format($row->percentange, 2) }}%</small>
                                </div>
                            </div>
                            @elseif($row->tax_type == 2)
                            <!-- Fixed Amount Tax Type -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="custom_fixed_amount_{{ $row->id }}" class="form-label">{{ __('field_custom_fixed_amount') }} ({!! $setting->currency_symbol !!})</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="custom_fixed_amount" id="custom_fixed_amount_{{ $row->id }}" placeholder="e.g., 100.00">
                                    <small class="text-muted">Default: {{ number_format($row->fixed_amount, 2) }} {!! $setting->currency_symbol !!}</small>
                                </div>
                            </div>
                            @endif
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="expires_at_{{ $row->id }}" class="form-label">{{ __('field_expires_at') }}</label>
                                    <input type="date" class="form-control" name="expires_at" id="expires_at_{{ $row->id }}">
                                    <small class="text-muted">{{ __('expires_at_help') }}</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> {{ __('btn_add') }} {{ __('exemption') }}
                        </button>
                    </form>

                    <hr>

                    <!-- Existing Exemptions List -->
                    <h6>{{ __('exempt_staff_list') }}</h6>
                    @if($row->exemptStaff->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('field_staff_id') }}</th>
                                    <th>{{ __('field_name') }}</th>
                                    <th>{{ __('field_custom_tax') }}</th>
                                    <th>{{ __('field_expires_at') }}</th>
                                    <th>{{ __('field_reason') }}</th>
                                    <th>{{ __('field_action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($row->exemptStaff as $staff)
                                @php
                                    $exemption = \App\Models\StaffTaxExemption::where('tax_setting_id', $row->id)
                                                                              ->where('user_id', $staff->id)
                                                                              ->first();
                                    $isExpired = $exemption && $exemption->isExpired();
                                @endphp
                                <tr class="{{ $isExpired ? 'table-warning' : '' }}">
                                    <td>{{ $staff->staff_id }}</td>
                                    <td>{{ $staff->first_name }} {{ $staff->last_name }}</td>
                                    <td>
                                        @if($row->tax_type == 1 && $exemption->custom_percentage)
                                            <span class="badge bg-info text-white">{{ number_format($exemption->custom_percentage, 2) }}%</span>
                                        @elseif($row->tax_type == 2 && $exemption->custom_fixed_amount)
                                            <span class="badge bg-warning text-white">{{ number_format($exemption->custom_fixed_amount, 2) }} {!! $setting->currency_symbol !!}</span>
                                        @else
                                            <span class="badge bg-success text-white">{{ __('fully_exempt') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($exemption->expires_at)
                                            {{ \Carbon\Carbon::parse($exemption->expires_at)->format('d M Y') }}
                                            @if($isExpired)
                                                <br><span class="badge bg-danger">{{ __('expired') }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ __('never') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $staff->pivot->reason ?? '-' }}</td>
                                    <td>
                                        <form action="{{ route('admin.tax-exemptions.destroy', ['tax_setting_id' => $row->id, 'user_id' => $staff->id]) }}" method="post" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ __('confirm_delete') }}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted"><i class="fas fa-info-circle"></i> {{ __('no_exemptions') }}</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                </div>
            </div>
        </div>
    </div>
