@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.detail-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.metric-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    text-align: center;
    height: 100%;
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
}

.metric-label {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
}

.detail-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.detail-card h5 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.detail-card h5 i {
    color: #667eea;
    margin-right: 10px;
}

.contributor-item {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 10px;
}

.contributor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #667eea;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-weight: 600;
}

.contributor-info {
    flex: 1;
}

.contributor-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
}

.pinned-message {
    background: #fffef0;
    border: 1px solid #ffe066;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
}

.pinned-message .pin-icon {
    color: #ffc107;
    margin-right: 8px;
}

.timeline-chart {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #eee;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #666;
}

.info-value {
    font-weight: 600;
    color: #333;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Header -->
        <div class="detail-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent p-0 mb-2">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.class-hub-reports.index') }}" class="text-white-50">Class Hub Reports</a>
                            </li>
                            <li class="breadcrumb-item active text-white">Session Detail</li>
                        </ol>
                    </nav>
                    <h2 class="mb-2">
                        {{ $classSession->subject->code ?? 'N/A' }} - {{ $classSession->subject->title ?? 'Session' }}
                    </h2>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-calendar mr-2"></i>{{ $classSession->date ? $classSession->date->format('l, F d, Y') : 'N/A' }}
                        | <i class="fas fa-user-tie ml-2 mr-1"></i>{{ $classSession->teacher->name ?? 'Unknown' }}
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('admin.class-hub-reports.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Metrics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-value">{{ $metrics['total_messages'] }}</div>
                    <div class="metric-label">Total Messages</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-value" style="color: #28a745;">{{ $metrics['unique_participants'] }}</div>
                    <div class="metric-label">Unique Participants</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-value" style="color: #ffc107;">{{ $metrics['total_alerts'] }}</div>
                    <div class="metric-label">Alerts Created</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-value" style="color: #17a2b8;">{{ $metrics['total_notes'] }}</div>
                    <div class="metric-label">Notes Taken</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-value" style="color: #dc3545;">{{ $metrics['total_questions'] }}</div>
                    <div class="metric-label">Questions Asked</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($metrics['attendance_rate'], 1) }}%</div>
                    <div class="metric-label">Attendance Rate</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Session Info -->
            <div class="col-md-4">
                <div class="detail-card">
                    <h5><i class="fas fa-info-circle"></i> Session Information</h5>
                    
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            @switch($classSession->status)
                                @case('scheduled')
                                    <span class="badge badge-secondary">Scheduled</span>
                                    @break
                                @case('in_progress')
                                    <span class="badge badge-success">In Progress</span>
                                    @break
                                @case('completed')
                                    <span class="badge badge-primary">Completed</span>
                                    @break
                                @default
                                    <span class="badge badge-light">{{ $classSession->status }}</span>
                            @endswitch
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Scheduled Time</span>
                        <span class="info-value">
                            {{ $classSession->start_time ? \Carbon\Carbon::parse($classSession->start_time)->format('H:i') : 'N/A' }} - 
                            {{ $classSession->end_time ? \Carbon\Carbon::parse($classSession->end_time)->format('H:i') : 'N/A' }}
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Actual Time</span>
                        <span class="info-value">
                            {{ $classSession->actual_start_time ? $classSession->actual_start_time->format('H:i') : 'N/A' }} - 
                            {{ $classSession->actual_end_time ? $classSession->actual_end_time->format('H:i') : 'N/A' }}
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Duration</span>
                        <span class="info-value">{{ $classSession->actual_duration_minutes ?? 0 }} min</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Chat Enabled</span>
                        <span class="info-value">
                            @if($classSession->is_chat_enabled)
                                <span class="badge badge-success">Yes</span>
                            @else
                                <span class="badge badge-danger">No</span>
                            @endif
                        </span>
                    </div>
                    
                    @if($classSession->topic_covered)
                    <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 10px;">
                        <strong class="text-primary">Topic Covered:</strong>
                        <p class="mb-0 mt-2">{{ $classSession->topic_covered }}</p>
                    </div>
                    @endif
                </div>

                <!-- Top Contributors -->
                <div class="detail-card">
                    <h5><i class="fas fa-users"></i> Top Contributors</h5>
                    @forelse($topContributors as $contributor)
                    <div class="contributor-item">
                        <div class="contributor-avatar">
                            @if($contributor->studentEnroll && $contributor->studentEnroll->student && $contributor->studentEnroll->student->photo)
                                <img src="{{ asset('uploads/student/' . $contributor->studentEnroll->student->photo) }}" 
                                     class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                            @else
                                {{ substr($contributor->studentEnroll->student->first_name ?? 'S', 0, 1) }}
                            @endif
                        </div>
                        <div class="contributor-info">
                            <strong>{{ $contributor->studentEnroll->student->first_name ?? '' }} {{ $contributor->studentEnroll->student->last_name ?? '' }}</strong>
                        </div>
                        <span class="contributor-count">{{ $contributor->message_count }} msgs</span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No chat activity</p>
                    @endforelse
                </div>
            </div>

            <!-- Messages and Activity -->
            <div class="col-md-8">
                <!-- Message Timeline Chart -->
                <div class="detail-card">
                    <h5><i class="fas fa-chart-area"></i> Message Activity by Hour</h5>
                    <div class="timeline-chart">
                        <canvas id="messageTimelineChart" height="150"></canvas>
                    </div>
                </div>

                <!-- Pinned Messages -->
                @if($pinnedMessages->count() > 0)
                <div class="detail-card">
                    <h5><i class="fas fa-thumbtack"></i> Pinned Messages</h5>
                    @foreach($pinnedMessages as $message)
                    <div class="pinned-message">
                        <div class="d-flex justify-content-between">
                            <div>
                                <i class="fas fa-thumbtack pin-icon"></i>
                                <strong>{{ $message->studentEnroll->student->first_name ?? '' }} {{ $message->studentEnroll->student->last_name ?? '' }}</strong>
                            </div>
                            <small class="text-muted">{{ $message->created_at->format('H:i') }}</small>
                        </div>
                        <p class="mb-0 mt-2">{{ $message->message }}</p>
                    </div>
                    @endforeach
                </div>
                @endif

                <!-- Alerts -->
                @if($classSession->alerts->count() > 0)
                <div class="detail-card">
                    <h5><i class="fas fa-bell"></i> Alerts Created ({{ $classSession->alerts->count() }})</h5>
                    @foreach($classSession->alerts as $alert)
                    <div class="p-3 mb-2" style="background: {{ $alert->alert_type == 'important' ? '#fff5f5' : '#f8f9fa' }}; border-radius: 10px; border-left: 4px solid {{ $alert->alert_type == 'important' ? '#dc3545' : '#667eea' }};">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $alert->title }}</strong>
                            <span class="badge badge-{{ $alert->alert_type == 'important' ? 'danger' : ($alert->alert_type == 'reminder' ? 'warning' : 'info') }}">
                                {{ ucfirst($alert->alert_type) }}
                            </span>
                        </div>
                        <p class="mb-1 mt-1">{{ $alert->description }}</p>
                        <small class="text-muted">
                            By: {{ $alert->createdBy->student->first_name ?? '' }} {{ $alert->createdBy->student->last_name ?? '' }}
                            | {{ $alert->upvote_count }} upvotes
                        </small>
                    </div>
                    @endforeach
                </div>
                @endif

                <!-- Questions -->
                @if($classSession->questions->count() > 0)
                <div class="detail-card">
                    <h5><i class="fas fa-question-circle"></i> Questions Asked ({{ $classSession->questions->count() }})</h5>
                    @foreach($classSession->questions as $question)
                    <div class="p-3 mb-2" style="background: #f8f9fa; border-radius: 10px;">
                        <div class="d-flex justify-content-between">
                            <strong>Q: {{ $question->question }}</strong>
                            @if($question->is_answered)
                                <span class="badge badge-success">Answered</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </div>
                        @if($question->answer)
                        <div class="mt-2 p-2" style="background: #e8f5e9; border-radius: 8px;">
                            <small class="text-success"><strong>A:</strong> {{ $question->answer }}</small>
                        </div>
                        @endif
                        <small class="text-muted mt-2 d-block">
                            {{ $question->is_anonymous ? 'Anonymous' : (($question->studentEnroll->student->first_name ?? '') . ' ' . ($question->studentEnroll->student->last_name ?? '')) }}
                        </small>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Message Timeline Chart
    const timelineData = @json($messageTimeline);
    
    const hours = Array.from({length: 24}, (_, i) => i);
    const messageCounts = hours.map(hour => {
        const found = timelineData.find(d => d.hour == hour);
        return found ? found.count : 0;
    });

    new Chart(document.getElementById('messageTimelineChart'), {
        type: 'line',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Messages',
                data: messageCounts,
                fill: true,
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderColor: 'rgba(102, 126, 234, 1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
@endsection
