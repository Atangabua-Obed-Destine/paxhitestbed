@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<link rel="stylesheet" href="{{ asset('dashboard/plugins/select2/css/select2.min.css') }}">
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Configure Maximum Credits') }}</h5>
                    </div>
                    <div class="card-block">
                        <form action="{{ route($route . '.store') }}" method="post" class="needs-validation" novalidate>
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="faculty_id" class="form-label">{{ trans_choice('module_faculty', 1) }} <span>*</span></label>
                                    <select name="faculty_id" id="faculty_id" class="form-control select2" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($faculties as $faculty)
                                            <option value="{{ $faculty->id }}" @selected(old('faculty_id') == $faculty->id)>{{ $faculty->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ trans_choice('module_faculty', 1) }}</div>
                                    @error('faculty_id')
                                        <small class="text-danger d-block">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="program_id" class="form-label">{{ trans_choice('module_program', 1) }} <span>*</span></label>
                                    <select name="program_id" id="program_id" class="form-control select2" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($programs as $program)
                                            <option value="{{ $program->id }}" data-faculty="{{ $program->faculty_id }}" @selected(old('program_id') == $program->id)>
                                                {{ $program->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ trans_choice('module_program', 1) }}</div>
                                    @error('program_id')
                                        <small class="text-danger d-block">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="session_id" class="form-label">{{ trans_choice('module_session', 1) }} <span>*</span></label>
                                    <select name="session_id" id="session_id" class="form-control select2" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $session)
                                            <option value="{{ $session->id }}" @selected(old('session_id') == $session->id)>{{ $session->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">{{ __('required_field') }} {{ trans_choice('module_session', 1) }}</div>
                                    @error('session_id')
                                        <small class="text-danger d-block">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="max_credit_hours" class="form-label">{{ __('Maximum Credits Per Semester') }}</label>
                                    <input type="number" name="max_credit_hours" id="max_credit_hours" class="form-control" value="{{ old('max_credit_hours') }}" min="0" step="1" placeholder="{{ __('Leave empty or 0 for unlimited') }}">
                                    @error('max_credit_hours')
                                        <small class="text-danger d-block">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="form-group col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> {{ __('Save Configuration') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Existing Configurations') }}</h5>
                    </div>
                    <div class="card-block">
                        <form action="{{ route($route . '.index') }}" method="get" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label for="filter_faculty" class="form-label">{{ trans_choice('module_faculty', 1) }}</label>
                                <select name="faculty" id="filter_faculty" class="form-control select2">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" @selected($filters['faculty'] == $faculty->id)>{{ $faculty->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_program" class="form-label">{{ trans_choice('module_program', 1) }}</label>
                                <select name="program" id="filter_program" class="form-control select2">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($programs as $program)
                                        <option value="{{ $program->id }}" data-faculty="{{ $program->faculty_id }}" @selected($filters['program'] == $program->id)>{{ $program->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_session" class="form-label">{{ trans_choice('module_session', 1) }}</label>
                                <select name="session" id="filter_session" class="form-control select2">
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($sessions as $session)
                                        <option value="{{ $session->id }}" @selected($filters['session'] == $session->id)>{{ $session->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i> {{ __('Filter') }}</button>
                                <a href="{{ route($route . '.index') }}" class="btn btn-outline-secondary"><i class="fas fa-sync"></i> {{ __('Reset') }}</a>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ trans_choice('module_faculty', 1) }}</th>
                                        <th>{{ trans_choice('module_program', 1) }}</th>
                                        <th>{{ trans_choice('module_session', 1) }}</th>
                                        <th>{{ __('Maximum Credits') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($configs as $index => $config)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $config->faculty->title ?? '-' }}</td>
                                            <td>{{ $config->program->title ?? '-' }}</td>
                                            <td>{{ $config->session->title ?? '-' }}</td>
                                            <td>
                                                @if($config->max_credit_hours)
                                                    {{ $config->max_credit_hours }}
                                                @else
                                                    <span class="badge bg-success">{{ __('Unlimited') }}</span>
                                                @endif
                                            </td>
                                            <td class="d-flex align-items-center gap-2">
                                                <form action="{{ route($route . '.update', $config) }}" method="post" class="d-flex align-items-center gap-2">
                                                    @csrf
                                                    @method('put')
                                                    <input type="number" name="max_credit_hours" class="form-control form-control-sm" min="0" step="1" value="{{ $config->max_credit_hours ?? '' }}" placeholder="{{ __('Unlimited') }}">
                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> {{ __('Update') }}</button>
                                                </form>
                                                <form action="{{ route($route . '.destroy', $config) }}" method="post" onsubmit="return confirm({{ json_encode(__('Are you sure you want to remove this configuration?')) }});">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">{{ __('No configurations found.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script src="{{ asset('dashboard/plugins/select2/js/select2.full.min.js') }}"></script>
<script type="text/javascript">
"use strict";
(function ($) {
    if (typeof $ === 'undefined') {
        return;
    }

    const cloneOptions = ($select) => $select.find('option').map(function () {
        return $(this).clone();
    });

    const rebuildProgramOptions = (facultyId, $target, originalOptions) => {
        const currentValue = $target.val();
        const normalizedFaculty = facultyId ? String(facultyId) : '';

        $target.empty();

        originalOptions.each(function () {
            const $option = $(this).clone();
            const optionValue = $option.attr('value');

            if (!optionValue) {
                $target.append($option);
                return;
            }

            const optionFaculty = $option.data('faculty');
            if (!normalizedFaculty || String(optionFaculty) === normalizedFaculty) {
                $target.append($option);
            }
        });

        if (currentValue && $target.find(`option[value="${currentValue}"]`).length) {
            $target.val(currentValue);
        } else {
            $target.val('');
        }

        $target.trigger('change.select2');
    };

    $(function () {
        $('.select2').select2({
            width: '100%'
        });

        const $facultySelect = $('#faculty_id');
        const $programSelect = $('#program_id');
        const $filterFacultySelect = $('#filter_faculty');
        const $filterProgramSelect = $('#filter_program');

        const originalProgramOptions = cloneOptions($programSelect);
        const originalFilterProgramOptions = cloneOptions($filterProgramSelect);

        const refreshProgramDisabling = () => {
            const facultyVal = $facultySelect.val();
            const filterFacultyVal = $filterFacultySelect.val();
            const programHasOptions = $programSelect.children('option').length > 1;
            const filterProgramHasOptions = $filterProgramSelect.children('option').length > 1;

            const disableMain = !facultyVal || !programHasOptions;
            const disableFilter = !filterFacultyVal || !filterProgramHasOptions;

            $programSelect.prop('disabled', disableMain);
            $filterProgramSelect.prop('disabled', disableFilter);

            if (disableMain) {
                $programSelect.val('');
            }
            if (disableFilter) {
                $filterProgramSelect.val('');
            }

            $programSelect.trigger('change.select2');
            $filterProgramSelect.trigger('change.select2');
        };

        rebuildProgramOptions($facultySelect.val(), $programSelect, originalProgramOptions);
        rebuildProgramOptions($filterFacultySelect.val(), $filterProgramSelect, originalFilterProgramOptions);
        refreshProgramDisabling();

        $facultySelect.on('change', function () {
            rebuildProgramOptions($(this).val(), $programSelect, originalProgramOptions);
            refreshProgramDisabling();
        });

        $filterFacultySelect.on('change', function () {
            rebuildProgramOptions($(this).val(), $filterProgramSelect, originalFilterProgramOptions);
            refreshProgramDisabling();
        });

        $programSelect.on('select2:open select2:close', refreshProgramDisabling);
        $filterProgramSelect.on('select2:open select2:close', refreshProgramDisabling);
    });
})(window.jQuery);
</script>
@endsection
