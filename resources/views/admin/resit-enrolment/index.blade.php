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

        {{--
            Sittings already raised that never received what they inherit.

            A different population from the worklist below: those students have
            no resit enrolment yet, these have one that is missing its marks.
            The enrolment route inherits on its own now, so nothing new arrives
            in this state and the panel disappears once the backlog is cleared.

            It is listed only when the PARENT holds a CA mark the sitting does
            not. A course the parent never had assessed has nothing to carry
            over, and a sitting whose attendance has merely fallen behind has
            inherited correctly — offering a repair for either would be
            offering a button that cannot help, or one that overwrites a
            sitting that is already right.
        --}}
        @if($gaps->isNotEmpty())
        <div class="row">
            <div class="col-sm-12">
                <div class="card border-warning">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h5 class="text-warning mb-1">
                                <i class="fas fa-exclamation-triangle"></i>
                                {{ __(':count resit sitting(s) are missing the marks they should have inherited', ['count' => $gaps->count()]) }}
                            </h5>
                            <span class="text-muted" style="font-size:13px">
                                {{ __('A resit sitting carries over the attendance and the continuous assessment already earned — the student is re-sitting the final, not the course. These were raised before that happened automatically.') }}
                            </span>
                        </div>
                        <form method="post" action="{{ route($route.'.repair-inheritance') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-warning"
                                    onclick="return confirm('{{ __('Carry the attendance and CA mark over for every sitting listed? This only adds what is missing.') }}')">
                                <i class="fas fa-wrench"></i> {{ __('Repair all') }}
                            </button>
                        </form>
                    </div>

                    <div class="card-block">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            {{ __('Until this is repaired the student has no exam row for the course at all, so they do not appear on the CA marking screen and the mark cannot be entered — the only thing that puts them there is taking exam attendance, which CA does not otherwise require.') }}
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered nowrap">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('Sitting') }}</th>
                                        <th>{{ __('Inherits from') }}</th>
                                        <th>{{ __('Courses missing their CA') }}</th>
                                        <th>{{ __('Repair will restore') }}</th>
                                        <th class="text-center">{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($gaps as $gap)
                                    <tr>
                                        <td>{{ $gap['enrolment']->matricule ?? optional($gap['enrolment']->student)->student_id }}</td>
                                        <td>
                                            {{ optional($gap['enrolment']->student)->first_name }}
                                            {{ optional($gap['enrolment']->student)->last_name }}
                                            <div class="text-muted" style="font-size:11px">
                                                {{ optional($gap['enrolment']->program)->shortcode ?? optional($gap['enrolment']->program)->title }}
                                            </div>
                                        </td>
                                        <td>
                                            {{ optional($gap['resit_semester'])->title }}
                                            <div class="text-muted" style="font-size:11px">
                                                {{ __('enrolment #:id', ['id' => $gap['enrolment']->id]) }}
                                            </div>
                                        </td>
                                        <td>
                                            {{ __('enrolment #:id', ['id' => $gap['parent_enrolment']->id]) }}
                                        </td>
                                        <td>
                                            @foreach($gap['missing'] as $course)
                                                <span class="badge bg-danger" title="{{ $course['subject_title'] }}">
                                                    {{ $course['subject_code'] }}
                                                </span>
                                                <span class="text-muted" style="font-size:11px">
                                                    {{ __('CA') }} {{ $course['ca'] }}@if(!$loop->last),&nbsp;@endif
                                                </span>
                                            @endforeach
                                        </td>
                                        <td style="font-size:12px">
                                            {{ trans_choice(':count CA mark|:count CA marks', count($gap['missing']), ['count' => count($gap['missing'])]) }}
                                            @if($gap['attendance_missing'] > 0)
                                                <div class="text-muted">
                                                    {{ __(':count attendance record(s)', ['count' => $gap['attendance_missing']]) }}
                                                </div>
                                            @endif
                                            @if($gap['marking_missing'] > 0)
                                                <div class="text-muted">
                                                    {{ __(':count subject marking(s)', ['count' => $gap['marking_missing']]) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <form method="post" action="{{ route($route.'.repair-inheritance') }}">
                                                @csrf
                                                <input type="hidden" name="enrolment_id" value="{{ $gap['enrolment']->id }}">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-wrench"></i> {{ __('Repair') }}
                                                </button>
                                            </form>
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
        @endif

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
