@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{--
    What raising the resit enrolment would do, before it is done.

    The preview is the whole interaction: the admin confirms a decision rather
    than a hope. It states what will be created, which courses, that no fee is
    charged, that the matricule is reused — and names any resit request whose
    state it would overwrite, because on this installation the students needing
    this had all DECLINED their resits, and overriding a decision the student
    recorded should never happen quietly.
--}}

@php
    $origin = $preview['origin'];
    $student = $origin->student;
    $courses = collect($preview['courses']);
    $alreadyRegistered = collect($preview['already_registered']);
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Raise a resit enrolment') }}</h5>
                        <span class="text-muted" style="font-size:13px">
                            {{ $origin->matricule ?? optional($student)->student_id }} —
                            {{ optional($student)->first_name }} {{ optional($student)->last_name }}
                        </span>
                    </div>

                    <div class="card-block">
                        @if(!$preview['ok'])
                            <div class="alert alert-danger">
                                <strong><i class="fas fa-ban"></i> {{ __('This cannot be raised') }}</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach($preview['blockers'] as $blocker)
                                        <li>{{ $blocker }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                        @else

                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <th style="width:45%">{{ __('Owed from') }}</th>
                                        <td>
                                            {{ optional($preview['origin_semester'])->title }}
                                            <div class="text-muted" style="font-size:11px">
                                                {{ optional($origin->session)->title }} —
                                                {{ __('enrolment #:id', ['id' => $origin->id]) }}
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Will be sat in') }}</th>
                                        <td><strong>{{ optional($preview['resit_semester'])->title }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('field_program') }}</th>
                                        <td>{{ optional($origin->program)->title }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('field_matricule') }}</th>
                                        <td>{{ $preview['matricule'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Enrolment to be created') }}</th>
                                        <td>
                                            @if($preview['existing_enrolment'])
                                                {{ __('none — adding to #:id', ['id' => $preview['existing_enrolment']->id]) }}
                                            @else
                                                {{ __('one, marked completed') }}
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="col-md-6">
                                <div class="alert alert-secondary" style="font-size:13px">
                                    <strong>{{ __('What this does and does not do') }}</strong>
                                    <ul class="mb-0 mt-2">
                                        @foreach($preview['notes'] as $note)
                                            <li>{{ $note }}</li>
                                        @endforeach
                                        <li>{{ __('The original registration, its marks and the student\'s current enrolment are not touched.') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        @if($preview['complete'])
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                {{ __('There is nothing left to raise for this enrolment.') }}
                            </div>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                            </a>
                        @else

                        <form method="post" action="{{ route($route.'.store') }}" id="resit-enrolment-form">
                            @csrf
                            <input type="hidden" name="enrollment_id" value="{{ $origin->id }}">

                            <h6 class="mt-3">{{ __('Courses to register') }}</h6>
                            <p class="text-muted" style="font-size:12px">
                                {{ __('Every course the student did not validate in that semester is ticked. Untick any that should not be sat.') }}
                            </p>

                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width:40px"></th>
                                            <th>{{ __('field_code') }}</th>
                                            <th>{{ __('field_title') }}</th>
                                            <th class="text-center">{{ __('field_credit_hour') }}</th>
                                            <th class="text-center">{{ __('Best mark') }}</th>
                                            <th class="text-center">{{ __('Pass mark') }}</th>
                                            <th class="text-center">{{ __('Attempts') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($courses as $course)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="subjects[]"
                                                       value="{{ $course['subject_id'] }}" checked
                                                       id="subject-{{ $course['subject_id'] }}">
                                            </td>
                                            <td><label for="subject-{{ $course['subject_id'] }}" class="mb-0">{{ $course['subject_code'] }}</label></td>
                                            <td>{{ $course['subject_title'] }}</td>
                                            <td class="text-center">{{ $course['credit_hours'] }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-danger">{{ $course['best_marks'] }}</span>
                                            </td>
                                            <td class="text-center">{{ $course['pass_mark'] }}</td>
                                            <td class="text-center">{{ $course['attempts'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($alreadyRegistered->isNotEmpty())
                                <div class="alert alert-info" style="font-size:13px">
                                    <strong>{{ __('Already registered on the resit enrolment, and left alone') }}</strong>
                                    <div class="mt-1">
                                        {{ $alreadyRegistered->pluck('subject_code')->implode(', ') }}
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> {{ __('Raise the resit enrolment') }}
                                </button>
                                <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                    {{ __('btn_cancel') }}
                                </a>
                            </div>
                        </form>

                        @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
