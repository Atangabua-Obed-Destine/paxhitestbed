@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.history-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.history-item {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border-left: 5px solid #667eea;
}

.history-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.history-item.attended { border-left-color: #28a745; }
.history-item.late { border-left-color: #ffc107; }
.history-item.absent { border-left-color: #dc3545; }

.subject-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.meta-info {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    color: #666;
    font-size: 0.9rem;
}

.meta-info i {
    color: #667eea;
}

.topic-preview {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 0.9rem;
}

.topic-preview strong {
    color: #667eea;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Header -->
        <div class="history-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-history mr-3"></i>{{ $title }}</h2>
                    <p class="mb-0 opacity-75">Browse all your past class sessions</p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('student.class-hub.index') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Today's Classes
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" action="{{ route('student.class-hub.history') }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><i class="fas fa-book mr-1"></i> Subject</label>
                            <select name="subject_id" class="form-control">
                                <option value="">All Subjects</option>
                                @foreach($subjects ?? [] as $subject)
                                <option value="{{ $subject->id }}" {{ ($filters['subject_id'] ?? '') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->code }} - {{ $subject->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><i class="fas fa-calendar mr-1"></i> From</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><i class="fas fa-calendar mr-1"></i> To</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><i class="fas fa-filter mr-1"></i> Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="completed" {{ ($filters['status'] ?? '') == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ ($filters['status'] ?? '') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label><i class="fas fa-search mr-1"></i> Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Topic, subject..." value="{{ $filters['search'] ?? '' }}">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results -->
        @forelse($sessions as $session)
        @php
            $attendanceClass = 'absent';
            if($session->myAttendance && $session->myAttendance->clock_in_time) {
                $attendanceClass = $session->myAttendance->is_late ? 'late' : 'attended';
            }
        @endphp
        <div class="history-item {{ $attendanceClass }}">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="subject-title">
                        {{ $session->subject->code ?? 'N/A' }} - {{ $session->subject->title ?? 'Unknown' }}
                        @if($session->is_extra_class)
                        <span class="badge badge-info">Extra</span>
                        @endif
                    </h5>
                    <div class="meta-info">
                        <span><i class="fas fa-calendar mr-1"></i> {{ $session->date->format('M d, Y') }}</span>
                        <span><i class="fas fa-clock mr-1"></i> 
                            {{ $session->actual_start_time ? $session->actual_start_time->format('H:i') : 'N/A' }} - 
                            {{ $session->actual_end_time ? $session->actual_end_time->format('H:i') : 'N/A' }}
                        </span>
                        <span><i class="fas fa-user-tie mr-1"></i> {{ $session->teacher->name ?? 'Unknown' }}</span>
                        @if($session->actual_duration_minutes)
                        <span><i class="fas fa-hourglass-half mr-1"></i> {{ $session->actual_duration_minutes }} min</span>
                        @endif
                    </div>
                    @if($session->topic_covered)
                    <div class="topic-preview">
                        <strong>Topic:</strong> {{ Str::limit($session->topic_covered, 100) }}
                    </div>
                    @endif
                </div>
                <div class="col-md-2 text-center">
                    @if($session->myAttendance && $session->myAttendance->clock_in_time)
                        @if($session->myAttendance->is_late)
                        <span class="badge badge-warning p-2">
                            <i class="fas fa-clock mr-1"></i> Late
                        </span>
                        @else
                        <span class="badge badge-success p-2">
                            <i class="fas fa-check mr-1"></i> Attended
                        </span>
                        @endif
                    @else
                        <span class="badge badge-danger p-2">
                            <i class="fas fa-times mr-1"></i> Absent
                        </span>
                    @endif
                </div>
                <div class="col-md-2 text-right">
                    <a href="{{ route('student.class-hub.session-details', $session->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye mr-1"></i> Details
                    </a>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
            <h5>No class sessions found</h5>
            <p class="text-muted">Try adjusting your filters</p>
        </div>
        @endforelse

        <!-- Pagination -->
        @if($sessions->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $sessions->appends($filters)->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
