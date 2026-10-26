{{--
  Charging an expense against a departmental budget allocation.

  Expense-only and edit-only, exactly as before — lifted out of the edit form
  unchanged so the shared transaction form can render it without knowing what
  it is. The behaviour, the ids the page script binds to, and the help text are
  untouched.
--}}

<div class="form-group col-md-4">
    <label for="budget_id">{{ __('field_budget') }}</label>
    <select class="form-control" name="budget_id" id="budget_id">
        <option value="">{{ __('select_optional') }}</option>
        @foreach( $activeBudgets as $budget )
        <option value="{{ $budget->id }}" @if( $row->budget_id == $budget->id ) selected @endif>
            {{ $budget->code }} - {{ $budget->title }}
        </option>
        @endforeach
    </select>
    <small class="form-text text-muted">{{ __('link_income_to_budget') }}</small>
</div>

<div class="form-group col-md-4">
    <label for="budget_allocation_id">{{ __('field_budget_allocation') }}</label>
    <select class="form-control" name="budget_allocation_id" id="budget_allocation_id">
        <option value="">{{ __('select_budget_first') }}</option>
    </select>
    <small class="form-text text-muted" id="allocation-remaining"></small>
</div>
