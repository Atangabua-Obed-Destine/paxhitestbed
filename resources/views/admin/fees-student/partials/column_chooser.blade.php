{{-- The column chooser, lifted out of the view unchanged.

     Every checkbox addresses a column by INDEX (data-column="5" and so on),
     which is why the table's sixteen columns keep their existing order. Move
     one and this hides the wrong column, silently.
--}}

                        <!-- Column Visibility Toggle -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="columnVisibilityBtn" data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-columns"></i> {{ __('columns') }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right p-3" aria-labelledby="columnVisibilityBtn" style="min-width: 220px; max-height: 400px; overflow-y: auto;">
                                <h6 class="dropdown-header">{{ __('show_hide_columns') }}</h6>
                                <div class="dropdown-divider"></div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-receipt" data-column="2" checked>
                                    <label class="form-check-label" for="col-receipt">{{ __('field_receipt') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-matricule" data-column="3" checked>
                                    <label class="form-check-label" for="col-matricule">{{ __('field_matricule') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-feetype" data-column="4" checked>
                                    <label class="form-check-label" for="col-feetype">{{ __('field_fees_type') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-fee" data-column="5" checked>
                                    <label class="form-check-label" for="col-fee">{{ __('field_fee') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-discount" data-column="6" checked>
                                    <label class="form-check-label" for="col-discount">{{ __('field_discount') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-fine" data-column="7" checked>
                                    <label class="form-check-label" for="col-fine">{{ __('field_fine_amount') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-net" data-column="8" checked>
                                    <label class="form-check-label" for="col-net">{{ __('field_net_amount') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-paid" data-column="9" checked>
                                    <label class="form-check-label" for="col-paid">{{ __('field_paid') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-balance" data-column="10" checked>
                                    <label class="form-check-label" for="col-balance">{{ __('field_remaining_balance') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-paydate" data-column="11" checked>
                                    <label class="form-check-label" for="col-paydate">{{ __('field_pay_date') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-status" data-column="12" checked>
                                    <label class="form-check-label" for="col-status">{{ __('field_status') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-method" data-column="13" checked>
                                    <label class="form-check-label" for="col-method">{{ __('field_payment_method') }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input column-toggle" type="checkbox" id="col-note" data-column="14" checked>
                                    <label class="form-check-label" for="col-note">{{ __('field_note') }}</label>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-xs btn-outline-primary" id="showAllColumns">{{ __('show_all') }}</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" id="hideOptionalColumns">{{ __('minimal') }}</button>
                                </div>
                            </div>
                        </div>
