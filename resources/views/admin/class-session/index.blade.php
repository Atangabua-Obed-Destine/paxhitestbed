@extends('admin.layouts.master')

@section('title', $title)

@push('css')
<link rel="stylesheet" href="{{ asset('dashboard/css/pages/admin-ui.css') }}?v={{ filemtime(public_path('dashboard/css/pages/admin-ui.css')) }}">
<style>
    /* Class sessions — spacing scale 8 · 16 · 24 px. */
    .cs-page .cs-filter .card-body { padding: 16px 24px 24px; }
    .cs-page .cs-filter label { margin-bottom: 8px; font-weight: 500; }
    .cs-page .cs-filter .select2-container { width: 100% !important; }
    .cs-page .cs-filter-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #e9ecef;
    }
    .cs-page .cs-filter-actions .btn { margin: 0; }
    .cs-page .btn i { margin-right: 4px; }

    .cs-page .cs-count {
        padding: 2px 10px;
        border-radius: 999px;
        background: #eef1f5;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
    }

    .cs-page .cs-table th { white-space: nowrap; vertical-align: middle; }
    .cs-page .cs-table td { vertical-align: middle; }
    .cs-page .cs-nowrap { white-space: nowrap; }
    .cs-page .cs-sub { display: block; margin-top: 2px; font-size: 12px; color: #6c757d; }
    .cs-page .cs-table .badge { white-space: nowrap; }
    .cs-page .cs-table .badge i { margin-right: 2px; }
    .cs-page .cs-tag { margin-left: 4px; vertical-align: middle; }

    /* One divider row per day, instead of the date on every row */
    .cs-page .cs-day-row td {
        padding: 8px 24px !important;
        background: #f4f6f9;
        border-top: 1px solid #e3e8ef;
        border-bottom: 1px solid #e3e8ef;
        font-size: 12px;
        color: #475569;
    }
    .cs-page .cs-day-row:hover td { background: #f4f6f9; }
    .cs-page .cs-day { font-weight: 600; color: #1f2937; }
    .cs-page .cs-day-count { margin-left: 8px; color: #6c757d; }
    .cs-page .cs-day-today {
        margin-left: 8px;
        padding: 1px 8px;
        border-radius: 999px;
        background: #e4f4fe;
        color: #0b8fd0;
        font-weight: 600;
    }
    .cs-page .cs-day-row.is-today td { background: #f0f8fe; }

    .cs-page .cs-empty { padding: 32px 16px !important; color: #6c757d; text-align: center; }
    .cs-page .cs-empty i { display: block; margin-bottom: 8px; font-size: 28px; opacity: .5; }
    .cs-page .cs-empty strong { display: block; margin-bottom: 4px; color: #343a40; }
</style>
@endpush

@section('content')

@php
    $count = fn ($status) => (int) ($status_counts[$status] ?? 0);
    $periodLabel = \Carbon\Carbon::parse($selected_date_from)->format('d M Y').' – '.\Carbon\Carbon::parse($selected_date_to)->format('d M Y');
@endphp

<div class="main-body">
    <div class="page-wrapper cs-page">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5>{{ $title }}</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ $title }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Filter -->
        <div class="card cs-filter">
            <div class="card-header ui-card-header">
                <h5>Filter</h5>
                <a href="{{ route($route.'.kiosk') }}" class="btn btn-success btn-sm">
                    <i class="fas fa-qrcode"></i> Open Kiosk
                </a>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route($route.'.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-2 col-md-4">
                            <label for="cs-program">Program</label>
                            <select name="program" id="cs-program" class="form-control select2">
                                <option value="0">-- All Programs --</option>
                                @foreach($programs as $program)
                                <option value="{{ $program->id }}" {{ $selected_program == $program->id ? 'selected' : '' }}>
                                    {{ $program->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="cs-session">Academic Year</label>
                            <select name="session" id="cs-session" class="form-control select2">
                                <option value="0">-- All Sessions --</option>
                                @foreach($sessions as $session)
                                <option value="{{ $session->id }}" {{ $selected_session == $session->id ? 'selected' : '' }}>
                                    {{ $session->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="cs-teacher">Teacher</label>
                            <select name="teacher" id="cs-teacher" class="form-control select2">
                                <option value="0">-- All Teachers --</option>
                                @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ $selected_teacher == $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->first_name }} {{ $teacher->last_name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="cs-from">Date From</label>
                            <input type="date" name="date_from" id="cs-from" class="form-control" value="{{ $selected_date_from }}">
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="cs-to">Date To</label>
                            <input type="date" name="date_to" id="cs-to" class="form-control" value="{{ $selected_date_to }}">
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label for="cs-status">Status</label>
                            <select name="status" id="cs-status" class="form-control">
                                <option value="">-- All --</option>
                                <option value="pending" {{ $selected_status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="in_progress" {{ $selected_status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="completed" {{ $selected_status == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ $selected_status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="cs-filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <a href="{{ route($route.'.index') }}" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary: everything the filters match -->
        <div class="row stat-cards">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <i class="fas fa-check-circle stat-card-icon"></i>
                        <h6 class="text-white">Completed Classes</h6>
                        <h2 class="text-white">{{ number_format($count('completed')) }}</h2>
                        <p>{{ $periodLabel }}</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <i class="fas fa-play-circle stat-card-icon"></i>
                        <h6 class="text-white">In Progress</h6>
                        <h2 class="text-white">{{ number_format($count('in_progress')) }}</h2>
                        <p>{{ $periodLabel }}</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <i class="fas fa-clock stat-card-icon"></i>
                        <h6 class="text-white">Pending</h6>
                        <h2 class="text-white">{{ number_format($count('pending')) }}</h2>
                        <p>{{ $periodLabel }}</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <i class="fas fa-times-circle stat-card-icon"></i>
                        <h6 class="text-white">Cancelled</h6>
                        <h2 class="text-white">{{ number_format($count('cancelled')) }}</h2>
                        <p>{{ $periodLabel }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sessions -->
        <div class="card">
            <div class="card-header ui-card-header">
                <h5>Class Sessions</h5>
                <span class="cs-count">{{ number_format($rows->total()) }} {{ $rows->total() == 1 ? 'record' : 'records' }}</span>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover ui-table-flush cs-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Program</th>
                            <th>Duration</th>
                            <th>Attendance</th>
                            <th>Logbook</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $perDay = $rows->getCollection()->countBy(fn ($r) => $r->date->format('Y-m-d'));
                            $lastDay = null;
                        @endphp
                        @forelse($rows as $row)
                        @php $day = $row->date->format('Y-m-d'); @endphp
                        @if($day !== $lastDay)
                        <tr class="cs-day-row {{ $row->date->isToday() ? 'is-today' : '' }}">
                            <td colspan="9">
                                <span class="cs-day">{{ $row->date->format('l, d M Y') }}</span>
                                @if($row->date->isToday())<span class="cs-day-today">Today</span>@endif
                                <span class="cs-day-count">{{ $perDay[$day] }} {{ $perDay[$day] == 1 ? 'session' : 'sessions' }}</span>
                            </td>
                        </tr>
                        @php $lastDay = $day; @endphp
                        @endif
                        <tr>
                            <td class="cs-nowrap">
                                {{ \Carbon\Carbon::parse($row->scheduled_start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($row->scheduled_end_time)->format('H:i') }}
                                @if($row->is_extra_class)
                                <span class="badge badge-info cs-tag">Extra</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $row->subject->code ?? 'N/A' }}</strong>
                                <span class="cs-sub">{{ $row->subject->title ?? '' }}</span>
                            </td>
                            <td>{{ $row->teacher->first_name ?? '' }} {{ $row->teacher->last_name ?? '' }}</td>
                            <td>
                                <span title="{{ $row->program->title ?? '' }}">{{ $row->program->shortcode ?? $row->program->title ?? 'N/A' }}</span>
                                @if($row->semester)
                                <span class="cs-sub">{{ $row->semester->title }}</span>
                                @endif
                            </td>
                            <td class="cs-nowrap">
                                @if($row->actual_duration_minutes)
                                    {{ $row->actual_duration_minutes }} mins
                                    <span class="cs-sub text-{{ $row->duration_percentage >= 70 ? 'success' : 'warning' }}">{{ $row->duration_percentage }}% of scheduled</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="cs-nowrap">
                                @if($row->status == 'completed')
                                    <span class="badge badge-success">{{ $row->present_count }}</span> Present
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($row->logbook && $row->logbook->is_completed)
                                    <span class="badge badge-success"><i class="fas fa-check"></i> Complete</span>
                                @elseif($row->logbook && $row->topic_covered)
                                    <span class="badge badge-warning"><i class="fas fa-edit"></i> Draft</span>
                                @else
                                    <span class="badge badge-secondary"><i class="fas fa-minus"></i> Empty</span>
                                @endif
                            </td>
                            <td>
                                @if($row->status == 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @elseif($row->status == 'in_progress')
                                    <span class="badge badge-success">In Progress</span>
                                @elseif($row->status == 'completed')
                                    <span class="badge badge-primary">Completed</span>
                                @elseif($row->status == 'cancelled')
                                    <span class="badge badge-danger">Cancelled</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-info btn-sm" title="View session">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="cs-empty">
                                <i class="fas fa-chalkboard"></i>
                                <strong>No class sessions found</strong>
                                Try a wider date range or fewer filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->total() > 0)
            <div class="card-footer ui-pagination-footer">
                <small class="text-muted">
                    Showing {{ number_format($rows->firstItem()) }}–{{ number_format($rows->lastItem()) }} of {{ number_format($rows->total()) }}
                </small>
                {{ $rows->appends(request()->query())->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
