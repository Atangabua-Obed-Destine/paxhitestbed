@extends('admin.layouts.master')

@section('title', $title)

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h1><i class="fas fa-chalkboard-teacher mr-2"></i>{{ $title }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <!-- Filter Card -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filters</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route($route.'.index') }}">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Program</label>
                                <select name="program" class="form-control select2">
                                    <option value="0">-- All Programs --</option>
                                    @foreach($programs as $program)
                                    <option value="{{ $program->id }}" {{ $selected_program == $program->id ? 'selected' : '' }}>
                                        {{ $program->title }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Academic Year</label>
                                <select name="session" class="form-control select2">
                                    <option value="0">-- All Sessions --</option>
                                    @foreach($sessions as $session)
                                    <option value="{{ $session->id }}" {{ $selected_session == $session->id ? 'selected' : '' }}>
                                        {{ $session->title }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Teacher</label>
                                <select name="teacher" class="form-control select2">
                                    <option value="0">-- All Teachers --</option>
                                    @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ $selected_teacher == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->first_name }} {{ $teacher->last_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="form-control" value="{{ $selected_date_from }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="form-control" value="{{ $selected_date_to }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">-- All --</option>
                                    <option value="pending" {{ $selected_status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="in_progress" {{ $selected_status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="completed" {{ $selected_status == 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ $selected_status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-redo mr-2"></i>Reset
                            </a>
                            <a href="{{ route($route.'.kiosk') }}" class="btn btn-success float-right">
                                <i class="fas fa-qrcode mr-2"></i>Open Kiosk
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $rows->where('status', 'completed')->count() }}</h3>
                        <p>Completed Classes</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $rows->where('status', 'in_progress')->count() }}</h3>
                        <p>In Progress</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $rows->where('status', 'pending')->count() }}</h3>
                        <p>Pending</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $rows->where('status', 'cancelled')->count() }}</h3>
                        <p>Cancelled</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Class Sessions ({{ $rows->total() }} records)</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Program</th>
                            <th>Duration</th>
                            <th>Attendance</th>
                            <th>Logbook</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->date->format('d M Y') }}</td>
                            <td>
                                {{ \Carbon\Carbon::parse($row->scheduled_start_time)->format('H:i') }} -
                                {{ \Carbon\Carbon::parse($row->scheduled_end_time)->format('H:i') }}
                                @if($row->is_extra_class)
                                <span class="badge badge-info">Extra</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $row->subject->code ?? 'N/A' }}</strong><br>
                                <small class="text-muted">{{ $row->subject->title ?? '' }}</small>
                            </td>
                            <td>{{ $row->teacher->first_name ?? '' }} {{ $row->teacher->last_name ?? '' }}</td>
                            <td>
                                {{ $row->program->short_form ?? $row->program->title ?? 'N/A' }}
                                @if($row->semester)
                                <br><small class="text-muted">{{ $row->semester->title }}</small>
                                @endif
                            </td>
                            <td>
                                @if($row->actual_duration_minutes)
                                    {{ $row->actual_duration_minutes }} mins
                                    <br>
                                    <small class="text-{{ $row->duration_percentage >= 70 ? 'success' : 'warning' }}">
                                        ({{ $row->duration_percentage }}%)
                                    </small>
                                @else
                                    <span class="text-muted">--</span>
                                @endif
                            </td>
                            <td>
                                @if($row->status == 'completed')
                                    <span class="badge badge-success">{{ $row->present_count }}</span> Present
                                @else
                                    <span class="text-muted">--</span>
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
                            <td>
                                <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No class sessions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $rows->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</section>
@endsection
