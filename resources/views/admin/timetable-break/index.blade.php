@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <p class="text-muted mb-0" style="max-width:60rem">
                            {{ __('Blocks of the week that are not classes, such as the midday break and Holy Mass or Friday club activities. They show on every class timetable, the printed timetables and each student\'s week, on the days that class has lessons.') }}
                        </p>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead><tr><th>{{ __('Break') }}</th><th>{{ __('Time') }}</th><th>{{ __('Days') }}</th><th></th></tr></thead>
                                <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td><strong>{{ $row->title }}</strong></td>
                                        <td>{{ substr($row->start_time, 0, 5) }} – {{ substr($row->end_time, 0, 5) }}</td>
                                        <td>{{ $row->dayNames() }}</td>
                                        <td class="text-end">
                                            @can('class-routine-create')
                                            <form method="post" action="{{ route('admin.timetable-break.destroy', $row->id) }}" onsubmit="return confirm('{{ __('Remove this break from every timetable?') }}')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash-alt"></i> {{ __('Remove') }}</button>
                                            </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted">{{ __('No breaks yet.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @can('class-routine-create')
                <div class="card">
                    <div class="card-header"><h5>{{ __('Add a break') }}</h5></div>
                    <div class="card-block">
                        @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif
                        <form method="post" action="{{ route('admin.timetable-break.store') }}">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="title">{{ __('Name') }}</label>
                                    <input class="form-control" id="title" name="title" required maxlength="191" value="{{ old('title') }}" placeholder="{{ __('e.g. Break (Holy Mass)') }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="start_time">{{ __('From') }}</label>
                                    <input class="form-control" type="time" id="start_time" name="start_time" required value="{{ old('start_time', '12:00') }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="end_time">{{ __('To') }}</label>
                                    <input class="form-control" type="time" id="end_time" name="end_time" required value="{{ old('end_time', '13:00') }}">
                                </div>
                            </div>
                            <label class="d-block">{{ __('Days (leave all unticked for every day)') }}</label>
                            <div class="mb-3">
                                @foreach($days as $num => $name)
                                <label class="me-3" style="margin-right:14px"><input type="checkbox" name="days[]" value="{{ $num }}" {{ in_array($num, (array) old('days', [])) ? 'checked' : '' }}> {{ __($name) }}</label>
                                @endforeach
                            </div>
                            <button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ __('Save break') }}</button>
                        </form>
                    </div>
                </div>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
