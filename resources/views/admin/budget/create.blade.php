@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form method="post" action="{{ route('admin.budget.store') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title">{{ __('field_title') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('title') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="type">{{ __('field_type') }} <span>*</span></label>
                                        <select class="form-control" name="type" id="type" required>
                                            <option value="">{{ __('select') }}</option>
                                            <option value="annual" {{ old('type') == 'annual' ? 'selected' : '' }}>{{ __('text_annual') }}</option>
                                            <option value="departmental" {{ old('type') == 'departmental' ? 'selected' : '' }}>{{ __('text_departmental') }}</option>
                                            <option value="project" {{ old('type') == 'project' ? 'selected' : '' }}>{{ __('text_project') }}</option>
                                        </select>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('type') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fiscal_year">{{ __('field_fiscal_year') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="fiscal_year" id="fiscal_year" value="{{ old('fiscal_year', date('Y')) }}" required>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('fiscal_year') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3" id="department_div" style="display: none;">
                                    <div class="form-group">
                                        <label for="department_id">{{ __('field_department') }} <span id="dept_required">*</span></label>
                                        <select class="form-control" name="department_id" id="department_id">
                                            <option value="">{{ __('select') }}</option>
                                            @foreach($departments as $department)
                                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->title }}</option>
                                            @endforeach
                                        </select>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('department_id') }}
                                        </div>
                                    </div>
                                </div>


                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="parent_id">{{ __('Belongs to') }}</label>
                                        <select class="form-control" name="parent_id" id="parent_id">
                                            <option value="">{{ __('Not part of an annual budget') }}</option>
                                            @foreach($annualBudgets ?? [] as $annual)
                                            <option value="{{ $annual->id }}" {{ old('parent_id', $row->parent_id ?? null) == $annual->id ? 'selected' : '' }}>
                                                {{ $annual->title }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">
                                            {{ __('Naming the annual budget this comes out of lets the sheet show how much of a line is delegated.') }}
                                        </small>
                                        <div class="invalid-feedback">{{ $errors->first('parent_id') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="start_date">{{ __('field_start_date') }} <span>*</span></label>
                                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ old('start_date') }}" required>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('start_date') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="end_date">{{ __('field_end_date') }} <span>*</span></label>
                                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ old('end_date') }}" required>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('end_date') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="total_amount">{{ __('field_total_amount') }} <span>*</span></label>
                                        <input type="number" step="0.01" min="0" class="form-control" name="total_amount" id="total_amount" value="{{ old('total_amount') }}" required>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('total_amount') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">{{ __('field_description') }}</label>
                                        <textarea class="form-control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('description') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="note">{{ __('field_note') }}</label>
                                        <textarea class="form-control" name="note" id="note" rows="2">{{ old('note') }}</textarea>
                                        
                                        <div class="invalid-feedback">
                                            {{ $errors->first('note') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">{{ __('btn_save') }}</button>
                                        <a href="{{ route('admin.budget.index') }}" class="btn btn-secondary">{{ __('btn_cancel') }}</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Show/hide department field based on type
        $('#type').on('change', function() {
            if ($(this).val() == 'departmental') {
                $('#department_div').show();
                $('#department_id').prop('required', true);
                $('#dept_required').show();
            } else {
                $('#department_div').hide();
                $('#department_id').prop('required', false);
                $('#dept_required').hide();
            }
        });

        // Trigger change on page load if type is already selected
        if ($('#type').val() == 'departmental') {
            $('#department_div').show();
            $('#department_id').prop('required', true);
            $('#dept_required').show();
        }
    });
</script>
@endsection
