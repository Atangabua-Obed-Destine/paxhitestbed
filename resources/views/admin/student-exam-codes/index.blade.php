@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">

        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('HND Exam Codes') }}</h5>
                        <p class="text-muted mb-0">
                            {{ __('The national exam code CNOENC issues for each student. Record them here and print the commission\'s list from the system.') }}
                        </p>
                    </div>
                    <div class="card-block">
                        <form method="get" action="{{ route('admin.student-exam-codes.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="session_id">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session_id" id="session_id">
                                        @foreach ($sessions as $session)
                                            <option value="{{ $session->id }}" @selected($selected_session == $session->id)>{{ $session->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-2">
                                    <label for="level">{{ __('Level') }}</label>
                                    <select class="form-control" name="level" id="level">
                                        <option value="1" @selected($selected_level == 1)>{{ __('Level 1') }}</option>
                                        <option value="2" @selected($selected_level == 2)>{{ __('Level 2') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="program_id">{{ __('field_program') }}</label>
                                    <select class="form-control select2" name="program_id" id="program_id">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach ($programs as $program)
                                            <option value="{{ $program->id }}" @selected($selected_program == $program->id)>{{ $program->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                    <a class="btn btn-secondary" href="{{ route('admin.student-exam-codes.pdf', ['session_id' => $selected_session, 'level' => $selected_level, 'program_id' => $selected_program]) }}">
                                        <i class="fas fa-file-pdf"></i> {{ __('Print the list') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">
                            {{ __(':coded of :total students have a code', ['coded' => $total_coded, 'total' => $total_students]) }}
                        </h5>
                        @can('student-exam-code-manage')
                            <button type="submit" form="exam-codes-form" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i>{{ __('Save codes') }}
                            </button>
                        @endcan
                    </div>
                    <div class="card-block">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <strong>{{ __('Nothing was saved.') }}</strong>
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($total_students === 0)
                            <p class="text-muted mb-0">{{ __('No students are enrolled for this year and level.') }}</p>
                        @else
                            @can('student-exam-code-manage')
                                <p class="text-muted">
                                    {{ __('Type each code straight down the column, then save. Emptying a box removes that student\'s code.') }}
                                </p>
                            @endcan

                            <form method="post" action="{{ route('admin.student-exam-codes.save') }}" id="exam-codes-form">
                                @csrf
                                <input type="hidden" name="session_id" value="{{ $selected_session }}">
                                <input type="hidden" name="level" value="{{ $selected_level }}">
                                <input type="hidden" name="program_id" value="{{ $selected_program }}">

                                @foreach ($groups as $programme => $students)
                                    <h6 class="mt-3">{{ __('Option') }}: {{ $programme }}</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width: 5%">{{ __('No') }}</th>
                                                    <th style="width: 18%">{{ __('field_matricule') }}</th>
                                                    <th>{{ __('Nom et Prénom') }}</th>
                                                    <th style="width: 25%">{{ __('Code') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($students as $index => $enroll)
                                                    <tr>
                                                        <td>{{ $index + 1 }}</td>
                                                        <td>{{ $enroll->matricule ?? $enroll->student->student_id ?? '' }}</td>
                                                        <td>{{ strtoupper(trim(($enroll->student->first_name ?? '') . ' ' . ($enroll->student->last_name ?? ''))) }}</td>
                                                        <td>
                                                            <input type="text"
                                                                   class="form-control form-control-sm text-uppercase"
                                                                   name="codes[{{ $enroll->student_id }}]"
                                                                   value="{{ old('codes.' . $enroll->student_id, $codes[$enroll->student_id] ?? '') }}"
                                                                   placeholder="HND…"
                                                                   maxlength="40"
                                                                   autocomplete="off"
                                                                   @cannot('student-exam-code-manage') readonly @endcannot>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach

                                @can('student-exam-code-manage')
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save codes') }}</button>
                                @endcan
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
