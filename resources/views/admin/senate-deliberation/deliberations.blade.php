@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-gavel"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Senate Deliberation') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Deliberations') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Dashboard') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Filter Deliberations') }}</h5>
                    </div>
                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.deliberations') }}">
                            <div class="row gx-2 align-items-end">
                                <div class="form-group col-md-4">
                                    <label for="session">{{ __('Academic Session') }}</label>
                                    <select class="form-control" name="session" id="session">
                                        <option value="0">{{ __('All Sessions') }}</option>
                                        @foreach($sessions as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="semester">{{ __('Semester') }}</label>
                                    <select class="form-control" name="semester" id="semester">
                                        <option value="0">{{ __('All Semesters') }}</option>
                                        @foreach($semesters as $sem)
                                        <option value="{{ $sem->id }}" @if($selected_semester == $sem->id) selected @endif>{{ $sem->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> {{ __('Filter') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Deliberations List -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> {{ __('Deliberation Sessions') }}</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createDelibModal">
                            <i class="fas fa-plus"></i> {{ __('New Deliberation') }}
                        </button>
                    </div>
                    <div class="card-block p-0">
                        @if($deliberations->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>{{ __('Meeting Number') }}</th>
                                        <th>{{ __('Session') }}</th>
                                        <th>{{ __('Semester') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Decision') }}</th>
                                        <th>{{ __('Programs') }}</th>
                                        <th>{{ __('Signatures') }}</th>
                                        <th>{{ __('Created By') }}</th>
                                        <th style="width:100px">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deliberations as $delib)
                                    <tr>
                                        <td><strong>{{ $delib->meeting_number }}</strong></td>
                                        <td>{{ $delib->session->title ?? '' }}</td>
                                        <td>{{ $delib->semester->title ?? '' }}</td>
                                        <td>{{ $delib->meeting_date ? $delib->meeting_date->format('d M Y') : '-' }}</td>
                                        <td>
                                            <span class="badge {{ \App\Models\SenateDeliberation::statusBadgeClass($delib->status) }}">
                                                {{ \App\Models\SenateDeliberation::statusLabels()[$delib->status] ?? ucfirst($delib->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ \App\Models\SenateDeliberation::decisionBadgeClass($delib->overall_decision) }}">
                                                {{ \App\Models\SenateDeliberation::decisionLabels()[$delib->overall_decision] ?? 'Pending' }}
                                            </span>
                                        </td>
                                        <td>{{ $delib->programs->count() }}</td>
                                        <td>{{ $delib->signatures->count() }}</td>
                                        <td>{{ $delib->creator->name ?? 'System' }}</td>
                                        <td>
                                            <a href="{{ route($route.'.show', $delib->id) }}" class="btn btn-sm btn-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($deliberations->hasPages())
                        <div class="card-footer">
                            {{ $deliberations->appends(request()->query())->links() }}
                        </div>
                        @endif
                        @else
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-gavel fa-3x mb-3"></i>
                            <p>{{ __('No deliberation sessions found.') }}</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDelibModal">
                                <i class="fas fa-plus"></i> {{ __('Create First Deliberation') }}
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- CREATE DELIBERATION MODAL --}}
<div class="modal fade" id="createDelibModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route($route.'.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> {{ __('Create Deliberation Session') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>{{ __('Academic Session') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="session_id" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($sessions as $sess)
                                <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>{{ __('Semester') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="semester_id" required>
                                <option value="">{{ __('select') }}</option>
                                @foreach($semesters as $sem)
                                <option value="{{ $sem->id }}" @if($selected_semester == $sem->id) selected @endif>{{ $sem->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Meeting Date') }}</label>
                        <input type="date" name="meeting_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Venue') }}</label>
                        <input type="text" name="venue" class="form-control" placeholder="e.g. Senate Hall, Main Campus">
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Chairperson') }}</label>
                        <input type="text" name="chairperson" class="form-control" placeholder="e.g. Prof. John Doe (Vice Chancellor)">
                    </div>
                    <div class="mb-3">
                        <label>{{ __('Registrar') }}</label>
                        <input type="text" name="registrar" class="form-control" placeholder="e.g. Dr. Jane Smith">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> {{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
