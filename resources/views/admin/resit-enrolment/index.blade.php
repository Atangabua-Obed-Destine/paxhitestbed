@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{--
    Who is owed a resit enrolment.

    A worklist rather than a student picker: the registrar's question here is
    "who still needs one", not "what does this one student owe". Every course
    listed comes from OutstandingCourses, the same service the carry-over list,
    the progression modal and the Senate reports read, so this screen cannot
    disagree with them about what a student owes.

    Nothing on this page writes. Each row leads to a preview.
--}}

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <span class="text-muted" style="font-size:13px">
                            {{ __('Students with a course they did not validate and no resit enrolment to sit it in.') }}
                        </span>
                    </div>

                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control select2" name="program" id="program">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach($programs as $program)
                                        <option value="{{ $program->id }}" @if($selected_program == $program->id) selected @endif>
                                            {{ $program->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="semester">{{ __('field_semester') }}</label>
                                    <select class="form-control select2" name="semester" id="semester">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach($semesters as $semester)
                                        <option value="{{ $semester->id }}" @if($selected_semester == $semester->id) selected @endif>
                                            {{ $semester->title }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="search">{{ __('field_matricule') }} / {{ __('field_name') }}</label>
                                    <input type="text" class="form-control" name="search" id="search"
                                           value="{{ $search }}" placeholder="{{ __('search') }}">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-info btn-filter d-block">
                                        <i class="fas fa-search"></i> {{ __('btn_search') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-block">
                        @if($rows->isEmpty())
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle"></i>
                                {{ __('Nobody is waiting for a resit enrolment. Every outstanding course already has one to be sat in.') }}
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                {{ __(':students student(s) are owed a resit enrolment, covering :courses course(s) in all.', [
                                    'students' => $rows->pluck('student.id')->unique()->count(),
                                    'courses' => $course_total,
                                ]) }}
                                <div class="mt-1" style="font-size:12px">
                                    {{ __('Raising one creates a separate enrolment in the resit semester. It does not charge a fee, does not reissue the matricule, and leaves the original registration and its marks exactly as they are.') }}
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped table-bordered nowrap">
                                    <thead>
                                        <tr>
                                            <th>{{ __('field_matricule') }}</th>
                                            <th>{{ __('field_name') }}</th>
                                            <th>{{ __('field_program') }}</th>
                                            <th>{{ __('Owed from') }}</th>
                                            <th>{{ __('Will be sat in') }}</th>
                                            <th>{{ __('Courses') }}</th>
                                            <th class="text-center">{{ __('field_action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $row)
                                        <tr>
                                            <td>{{ $row['origin']->matricule ?? $row['student']->student_id }}</td>
                                            <td>{{ $row['student']->first_name }} {{ $row['student']->last_name }}</td>
                                            <td>{{ optional($row['origin']->program)->shortcode ?? optional($row['origin']->program)->title }}</td>
                                            <td>
                                                {{ optional($row['origin']->semester)->title }}
                                                <div class="text-muted" style="font-size:11px">
                                                    {{ optional($row['origin']->session)->title }}
                                                </div>
                                            </td>
                                            <td>
                                                @if($row['resit_semester'])
                                                    {{ $row['resit_semester']->title }}
                                                    @if($row['existing_enrolment'])
                                                        <div class="text-muted" style="font-size:11px">
                                                            {{ __('adding to enrolment #:id', ['id' => $row['existing_enrolment']->id]) }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="badge bg-danger">{{ __('no resit semester') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">{{ $row['course_count'] }}</span>
                                                <div style="font-size:11px">
                                                    @foreach($row['courses'] as $course)
                                                        <span title="{{ $course['subject_title'] }} — {{ __('best mark') }} {{ $course['best_marks'] }}/{{ $course['pass_mark'] }}">
                                                            {{ $course['subject_code'] }}<span class="text-muted">({{ $course['best_marks'] }})</span>@if(!$loop->last), @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                @if($row['resit_semester'])
                                                <a href="{{ route($route.'.show', $row['origin']->id) }}"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> {{ __('Review') }}
                                                </a>
                                                @else
                                                <span class="text-muted" style="font-size:11px">
                                                    {{ __('Create the resit semester first') }}
                                                </span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
