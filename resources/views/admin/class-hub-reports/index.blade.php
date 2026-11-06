@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px 16px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    text-align: center;
    height: 100%;
}

.stat-number {
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 4px;
    font-variant-numeric: tabular-nums;
}

.stat-number.primary { color: #04a9f5; }
.stat-number.success { color: #28a745; }
.stat-number.info { color: #17a2b8; }
.stat-number.warning { color: #ffc107; }
.stat-number.danger { color: #dc3545; }

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.report-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.report-card h5 {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #333;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #eef0f3;
}

.report-card h5 i {
    color: #04a9f5;
}

.filter-section {
    background: white;
    padding: 16px 24px 24px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.filter-section .ch-filter-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 -24px 16px;
    padding: 0 24px 16px;
    border-bottom: 1px solid #eef0f3;
}
.filter-section .ch-filter-head h5 { margin: 0; }
.filter-section .form-group { margin-bottom: 0; }
.filter-section label { margin-bottom: 8px; }
.filter-section .ch-filter-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #eef0f3;
}
.filter-section .btn,
.report-card .btn { margin: 0; }
.ch-page .btn i { margin-right: 4px; }
.ch-page .stats-row > [class*="col-"] { margin-bottom: 24px; }
.ch-page .report-card table td,
.ch-page .report-card table th { vertical-align: middle; }
.ch-page .ch-nowrap { white-space: nowrap; }
.filter-section select.form-control,
.filter-section input.form-control { width: 100%; }
.filter-section .ch-filter-head small { font-size: 13px; }
/* The shared Year field brings its own col-md-3 wrapper; here it already sits in a column. */
.filter-section .form-group.col-md-3 { flex: 0 0 100%; width: 100%; max-width: 100%; padding: 0; }

.engagement-item {
    padding: 15px;
    border-radius: 10px;
    background: #f8f9fa;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.engagement-item:hover {
    background: #e9ecef;
}

.engagement-badge {
    background: #e4f4fe;
    color: #0b8fd0;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.chart-container {
    position: relative;
    height: 300px;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper ch-page">
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

        <!-- Filters -->
        <div class="filter-section">
            <div class="ch-filter-head">
                <h5>Filter <small class="text-muted fw-normal ms-2">Monitor student engagement in live class sessions</small></h5>
                <a href="{{ route('admin.class-hub-reports.export') }}?date_from={{ request('date_from', now()->subMonth()->format('Y-m-d')) }}&date_to={{ request('date_to', now()->format('Y-m-d')) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-download"></i> Export Report
                </a>
            </div>
            <form method="GET" action="{{ route('admin.class-hub-reports.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-2 col-md-4">
                        <div class="form-group">
                            <label>Session</label>
                            <select name="session_id" class="form-control">
                                <option value="">All Sessions</option>
                                @foreach($sessions as $session)
                                <option value="{{ $session->id }}" {{ request('session_id') == $session->id ? 'selected' : '' }}>
                                    {{ $session->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        @include('common.inc.semester_year_field', ['year_target' => '.sy-semester'])
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester_id" class="form-control sy-semester">
                                <option value="">All Semesters</option>
                                @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>
                                    {{ $semester->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <div class="form-group">
                            <label>Program</label>
                            <select name="program_id" class="form-control">
                                <option value="">All Programs</option>
                                @foreach($programs as $program)
                                <option value="{{ $program->id }}" {{ request('program_id') == $program->id ? 'selected' : '' }}>
                                    {{ $program->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
                <div class="ch-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="{{ route('admin.class-hub-reports.index') }}" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="row stats-row">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="stat-card">
                    <div class="stat-number primary">{{ number_format($totalSessions) }}</div>
                    <div class="stat-label">Total Sessions</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="stat-card">
                    <div class="stat-number success">{{ number_format($completedSessions) }}</div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="stat-card">
                    <div class="stat-number info">{{ number_format($totalMessages) }}</div>
                    <div class="stat-label">Chat Messages</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="stat-card">
                    <div class="stat-number warning">{{ number_format($totalAlerts) }}</div>
                    <div class="stat-label">Alerts Posted</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="stat-card">
                    <div class="stat-number danger">{{ number_format($totalNotes) }}</div>
                    <div class="stat-label">Notes Taken</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="stat-card">
                    <div class="stat-number primary">{{ number_format($activeChatSessions) }}</div>
                    <div class="stat-label">Active Chat Sessions</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top Sessions by Engagement -->
            <div class="col-md-8">
                <div class="report-card">
                    <h5><i class="fas fa-trophy"></i> Top Sessions by Engagement</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th class="text-center">Messages</th>
                                    <th class="text-center">Alerts</th>
                                    <th class="text-center">Notes</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topSessions as $session)
                                <tr>
                                    <td class="ch-nowrap">{{ $session->date ? $session->date->format('d M Y') : 'N/A' }}</td>
                                    <td>
                                        <strong>{{ $session->subject->code ?? 'N/A' }}</strong>
                                        <br><small class="text-muted">{{ $session->subject->title ?? '' }}</small>
                                    </td>
                                    <td>{{ $session->teacher ? ($session->teacher->first_name . ' ' . $session->teacher->last_name) : 'N/A' }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-primary">{{ $session->messages_count ?? 0 }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-warning">{{ $session->alerts_count ?? 0 }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-info">{{ $session->notes_count ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.class-hub-reports.session', $session->id) }}" 
                                           class="btn btn-outline-primary btn-sm ch-nowrap">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        No sessions found with engagement data
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Subjects by Chat Activity -->
            <div class="col-md-4">
                <div class="report-card">
                    <h5><i class="fas fa-comments"></i> Top Subjects by Chat</h5>
                    @forelse($topSubjectsByChat as $item)
                    <div class="engagement-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $item->subject->code ?? 'N/A' }}</strong>
                            <br><small class="text-muted">{{ Str::limit($item->subject->title ?? '', 25) }}</small>
                        </div>
                        <span class="engagement-badge">
                            {{ number_format($item->total_messages) }} msgs
                        </span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-4">No chat activity yet</p>
                    @endforelse
                </div>

                <!-- Alert Types Distribution -->
                <div class="report-card">
                    <h5><i class="fas fa-bell"></i> Alert Types</h5>
                    @forelse($alertTypes as $type)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-{{ $type->alert_type == 'important' ? 'danger' : ($type->alert_type == 'reminder' ? 'warning' : 'info') }}">
                            {{ ucfirst($type->alert_type) }}
                        </span>
                        <span class="font-weight-bold">{{ $type->count }}</span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-2">No alerts posted</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Engagement -->
        <div class="report-card">
            <h5><i class="fas fa-clock"></i> Recent Session Activity</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Last Activity</th>
                            <th class="text-center">Messages</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEngagement as $session)
                        <tr>
                            <td>
                                <strong>{{ $session->subject->code ?? 'N/A' }}</strong> - {{ $session->subject->title ?? '' }}
                            </td>
                            <td class="ch-nowrap">{{ $session->date ? $session->date->format('d M Y') : 'N/A' }}</td>
                            <td>{{ $session->updated_at ? $session->updated_at->diffForHumans() : 'N/A' }}</td>
                            <td class="text-center">
                                <span class="badge badge-primary">{{ $session->messages_count ?? 0 }}</span>
                            </td>
                            <td>
                                @switch($session->status)
                                    @case('in_progress')
                                        <span class="badge badge-success">Live</span>
                                        @break
                                    @case('completed')
                                        <span class="badge badge-secondary">Completed</span>
                                        @break
                                    @default
                                        <span class="badge badge-light">{{ ucfirst($session->status) }}</span>
                                @endswitch
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No recent activity</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
