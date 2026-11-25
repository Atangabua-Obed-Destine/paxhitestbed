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
                        <p class="text-muted mb-0" style="max-width:62rem">
                            {{ __('A course group links courses that are one course taught to several programmes under each programme\'s own code. Each course keeps its own code, credits, marks and transcript. Joint classes, class sessions, exam timetables and the shared mark sheet treat the group as one course.') }}
                        </p>
                    </div>
                    <div class="card-block">
                        @can($access.'-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('btn_add_new') }}</a>
                        @endcan
                        <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                    </div>
                    <div class="card-block">
                        @if($rows->isEmpty())
                        <p class="text-muted">{{ __('No course groups yet.') }}</p>
                        @else
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('Group') }}</th>
                                        <th>{{ __('Courses (code, programmes, credits)') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <td style="min-width:14rem">
                                            <strong>{{ $row->title }}</strong>
                                            @if($row->note)<div class="text-muted small">{{ $row->note }}</div>@endif
                                        </td>
                                        <td>
                                            @foreach($row->subjects as $s)
                                            <div>
                                                <span class="badge badge-light" style="font-family:monospace">{{ $s->code }}</span>
                                                {{ $s->title }}
                                                <span class="text-muted small">·
                                                    {{ collect($programmes[$s->id] ?? [])->map(fn ($p) => $programNames[$p] ?? $p)->sort()->implode(', ') ?: __('no programme linked') }}
                                                    · {{ trans_choice('{1}:count credit|[2,*]:count credits', (int) $s->credit_hour, ['count' => $s->credit_hour]) }}
                                                </span>
                                            </div>
                                            @endforeach
                                        </td>
                                        <td style="white-space:nowrap">
                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm" title="{{ __('Edit') }}"><i class="far fa-edit"></i></a>
                                            @endcan
                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}" title="{{ __('Remove the group') }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            @include('admin.layouts.inc.delete')
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mb-0">{{ __('Removing a group only unlinks its courses. No course, class or mark is changed.') }}</p>
                        @endif
                    </div>
                </div>

                @if($suggestions->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Possible groups') }}</h5>
                        <p class="text-muted mb-0">{{ __('Courses outside any group with the same title and level, taken by different programmes. Check each one: two courses can share a name and still be different courses.') }}</p>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                @foreach($suggestions as $g)
                                    <tr>
                                        <td>
                                            @foreach($g as $s)
                                            <span class="badge badge-light" style="font-family:monospace">{{ $s->code }}</span> {{ $s->title }}@if(!$loop->last)<span class="text-muted"> · </span>@endif
                                            @endforeach
                                        </td>
                                        <td style="white-space:nowrap">
                                            @can($access.'-create')
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route($route.'.create', ['subjects' => $g->pluck('id')->all()]) }}">{{ __('Group these') }}</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
