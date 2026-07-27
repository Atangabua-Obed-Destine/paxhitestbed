@extends('admin.layouts.master')
@section('title', __('Sync Stuck Students'))
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Sync Stuck Students') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="alert alert-info">
                            <strong>{{ __('Information:') }}</strong>
                            {{ __('The following students have scheduled resit requests but are not enrolled in the target resit semester with the appropriate courses registered. This usually happens if the request was scheduled before the automated progression rules were implemented.') }}
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Student') }}</th>
                                        <th>{{ __('Course') }}</th>
                                        <th>{{ __('Resit Session / Semester') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($stuckRequests as $key => $request)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            {{ $request->studentEnroll->student->first_name ?? '' }} {{ $request->studentEnroll->student->last_name ?? '' }}<br>
                                            <small class="text-muted">{{ $request->studentEnroll->matricule ?? '' }}</small>
                                        </td>
                                        <td>
                                            {{ $request->subject->code ?? '' }} - {{ $request->subject->title ?? '' }}
                                        </td>
                                        <td>
                                            {{ $request->resitSession->title ?? '' }}<br>
                                            <small class="text-muted">{{ $request->resitSemester->title ?? '' }}</small>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center">{{ __('No stuck students found.') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            <form action="{{ route('admin.resit-requests.sync-stuck.execute') }}" method="POST" style="display:inline-block;">
                                @csrf
                                <button type="submit" class="btn btn-primary" @if(empty($stuckRequests)) disabled @endif>
                                    <i class="fas fa-sync"></i> {{ __('Sync All Students') }}
                                </button>
                            </form>
                            <a href="{{ route('admin.resit-requests.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
