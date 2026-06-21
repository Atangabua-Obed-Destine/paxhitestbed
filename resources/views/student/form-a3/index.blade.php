@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Current Program Info -->
            @if(isset($selected_enrollment) && $selected_enrollment)
            <div class="col-12 mb-3">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i>
                    <strong>Showing Form A3 for:</strong> 
                    {{ $selected_enrollment->program->title ?? 'Unknown Program' }} 
                    ({{ $selected_enrollment->matricule }})
                    <a href="{{ route('student.select-program') }}" class="float-right">
                        <i class="fas fa-exchange-alt"></i> Switch Program
                    </a>
                </div>
            </div>
            @endif
            
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span class="d-block m-t-5">{{ __('Course Registration Forms (Form A3) generated when you register courses') }}</span>
                    </div>
                    <div class="card-block">
                        <!-- Filter Form -->
                        <form class="needs-validation" novalidate method="get" action="{{ route('student.form-a3.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-4">
                                    <label for="session_id">{{ __('field_session') }}</label>
                                    <select class="form-control" name="session_id" id="session_id">
                                        <option value="">{{ __('All Sessions') }}</option>
                                        @foreach($sessions as $session)
                                        <option value="{{ $session->id }}" @if($selected_session == $session->id) selected @endif>
                                            {{ $session->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="semester_id">{{ __('field_semester') }}</label>
                                    <select class="form-control" name="semester_id" id="semester_id">
                                        <option value="">{{ __('All Semesters') }}</option>
                                        @foreach($semesters as $semester)
                                        <option value="{{ $semester->id }}" @if($selected_semester == $semester->id) selected @endif>
                                            {{ $semester->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label>&nbsp;</label><br>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
                                    <a href="{{ route('student.form-a3.index') }}" class="btn btn-secondary"><i class="fas fa-undo"></i> {{ __('Reset') }}</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(count($records) > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Your Form A3 Documents') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('Courses') }}</th>
                                        <th>{{ __('Total Credits') }}</th>
                                        <th>{{ __('Generated On') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($records as $key => $record)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $record->session->title ?? 'N/A' }}</td>
                                        <td>{{ $record->semester->title ?? 'N/A' }}</td>
                                        <td>{{ $record->enrollment->program->title ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-primary">{{ count($record->subjects_snapshot ?? []) }} {{ __('courses') }}</span>
                                        </td>
                                        <td>{{ $record->total_credits }}</td>
                                        <td>{{ $record->created_at->format('d M Y, h:i A') }}</td>
                                        <td>
                                            <a href="{{ route('student.form-a3.download', $record->id) }}" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-eye"></i> {{ __('View') }}
                                            </a>
                                            <a href="{{ route('student.form-a3.download', $record->id) }}" class="btn btn-sm btn-primary" target="_blank" onclick="window.print();">
                                                <i class="fas fa-print"></i> {{ __('Print') }}
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> {{ __('No Form A3 documents found. Form A3 is automatically generated when you register your courses for a semester.') }}
                </div>
            </div>
        </div>
        @endif

        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
