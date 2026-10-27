{{-- The report's filters, lifted out of the view unchanged.

     common/inc/fees_search_filter is shared with fees-master,
     fee-assignments-history and report/fees, so its markup stays exactly as
     it was; only the styling around it differs, and that is scoped to .fees.
--}}

                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.report') }}">
                            <div class="row gx-2">

                                {{-- No Year select here: it filtered nothing (report() never reads
                                     semester_year), it was empty until a programme was chosen, and
                                     being `required` it blocked the search button on a fresh load.
                                     The Semester select alone is what this report filters by. --}}
                                @include('common.inc.fees_search_filter', ['show_semester_year' => false])

                                <div class="form-group col-md-2">
                                    <label for="category">{{ __('field_fees_type') }}</label>
                                    <select class="form-control select2" name="category[]" id="category" multiple data-placeholder="{{ __('all') }}">
                                        @foreach( $categories as $category )
                                        <option value="{{ $category->id }}" @selected(in_array($category->id, (array) $selected_category))>{{ $category->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="payment_status">{{ __('filter_payment_status') }}</label>
                                    <select class="form-control select2" name="payment_status[]" id="payment_status" multiple data-placeholder="{{ __('all_payment_statuses') }}">
                                        <option value="0" @selected(in_array('0', (array) $selected_payment_status, true))>{{ __('filter_unpaid_fees') }}</option>
                                        <option value="1" @selected(in_array('1', (array) $selected_payment_status, true))>{{ __('filter_fully_paid_fees') }}</option>
                                        <option value="2" @selected(in_array('2', (array) $selected_payment_status, true))>{{ __('filter_partially_paid_fees') }}</option>
                                        <option value="3" @selected(in_array('3', (array) $selected_payment_status, true))>{{ __('status_canceled') }}</option>
                                        <option value="4" @selected(in_array('4', (array) $selected_payment_status, true))>{{ __('payment_plan') }}</option>
                                        <option value="5" @selected(in_array('5', (array) $selected_payment_status, true))>{{ __('filter_overpaid_fees') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="student_id">{{ __('field_student_id') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id" value="{{ $selected_student_id }}">
                                </div>

                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
