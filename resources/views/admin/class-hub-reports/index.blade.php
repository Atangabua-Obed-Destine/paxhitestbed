@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.report-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    text-align: center;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-number.primary { color: #667eea; }
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
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.report-card h5 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.report-card h5 i {
    color: #667eea;
    margin-right: 10px;
}

.filter-section {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
}

.chart-container {
    position: relative;
    height: 300px;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-chart-line mr-3"></i>{{ $title }}</h2>
                    <p class="mb-0 opacity-75">Monitor student engagement in live class sessions</p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('admin.class-hub-reports.export') }}?date_from={{ request('date_from', now()->subMonth()->format('Y-m-d')) }}&date_to={{ request('date_to', now()->format('Y-m-d')) }}" 
                       class="btn btn-light">
                        <i class="fas fa-download mr-1"></i> Export Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="{{ route('admin.class-hub-reports.index') }}">
                <div class="row">
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester_id" class="form-control">
                                <option value="">All Semesters</option>
                                @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>
                                    {{ $semester->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-filter mr-1"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number primary">{{ number_format($totalSessions) }}</div>
                    <div class="stat-label">Total Sessions</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number success">{{ number_format($completedSessions) }}</div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number info">{{ number_format($totalMessages) }}</div>
                    <div class="stat-label">Chat Messages</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number warning">{{ number_format($totalAlerts) }}</div>
                    <div class="stat-label">Alerts Posted</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number danger">{{ number_format($totalNotes) }}</div>
                    <div class="stat-label">Notes Taken</div>
                </div>
            </div>
            <div class="col-md-2">
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
                                    <td>{{ $session->date ? $session->date->format('M d, Y') : 'N/A' }}</td>
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
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
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
                            <td>{{ $session->date ? $session->date->format('M d, Y') : 'N/A' }}</td>
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
