@extends('admin.layouts.master')
@section('title', __('Security Logs'))
@section('content')

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-file-alt text-primary"></i> {{ __('Security Logs') }}</h3>
                        <p class="text-muted mb-0">{{ __('View and analyze failed login attempts') }}</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger" id="clearLogsBtn">
                            <i class="fas fa-trash"></i> {{ __('Clear Old Logs') }}
                        </button>
                        <a href="{{ route('admin.security.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card card-info card-outline collapsed-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-filter"></i> {{ __('Advanced Filters') }}
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.security.logs') }}" id="filterForm">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="email">{{ __('Email') }}</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               value="{{ request('email') }}"
                                               placeholder="{{ __('Search by email...') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="ip_address">{{ __('IP Address') }}</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="ip_address" 
                                               name="ip_address" 
                                               value="{{ request('ip_address') }}"
                                               placeholder="{{ __('Search by IP...') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="user_type">{{ __('User Type') }}</label>
                                        <select class="form-control" id="user_type" name="user_type">
                                            <option value="">{{ __('All Types') }}</option>
                                            <option value="admin" {{ request('user_type') === 'admin' ? 'selected' : '' }}>{{ __('Admin') }}</option>
                                            <option value="student" {{ request('user_type') === 'student' ? 'selected' : '' }}>{{ __('Student') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_from">{{ __('Date From') }}</label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="date_from" 
                                               name="date_from" 
                                               value="{{ request('date_from') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_to">{{ __('Date To') }}</label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="date_to" 
                                               name="date_to" 
                                               value="{{ request('date_to') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> {{ __('Apply Filters') }}
                                    </button>
                                    <a href="{{ route('admin.security.logs') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> {{ __('Clear Filters') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="row">
            <div class="col-12">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i> {{ __('Failed Login Attempts') }}
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-warning">{{ $logs->total() }} {{ __('records') }}</span>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('IP Address') }}</th>
                                    <th>{{ __('User Agent') }}</th>
                                    <th>{{ __('User Type') }}</th>
                                    <th>{{ __('Attempts') }}</th>
                                    <th>{{ __('Last Attempt') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr class="{{ $log->isBlocked() ? 'table-danger' : '' }}">
                                    <td>{{ $log->id }}</td>
                                    <td>
                                        <span class="text-break">{{ $log->email }}</span>
                                    </td>
                                    <td>
                                        <code>{{ $log->ip_address }}</code>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="{{ $log->user_agent }}">
                                            {{ Str::limit($log->user_agent, 30) }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $log->user_type === 'admin' ? 'primary' : 'info' }}">
                                            {{ ucfirst($log->user_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $log->attempts >= 5 ? 'danger' : 'warning' }}">
                                            {{ $log->attempts }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            {{ $log->last_attempt_at->format('M d, Y H:i:s') }}<br>
                                            <span class="text-muted">{{ $log->last_attempt_at->diffForHumans() }}</span>
                                        </small>
                                    </td>
                                    <td>
                                        @if($log->isBlocked())
                                        <span class="badge badge-danger">
                                            <i class="fas fa-lock"></i> {{ __('Blocked') }}
                                            <br><small>({{ $log->getRemainingBlockTime() }} min)</small>
                                        </span>
                                        @else
                                        <span class="badge badge-warning">
                                            <i class="fas fa-exclamation-triangle"></i> {{ __('Active') }}
                                        </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-shield-alt fa-3x mb-2"></i>
                                        <p>{{ __('No security logs found') }}</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer clearfix">
                        {{ $logs->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        @if($logs->total() > 0)
        <div class="row">
            <div class="col-md-3">
                <div class="info-box bg-warning">
                    <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Total Attempts') }}</span>
                        <span class="info-box-number">{{ $logs->sum('attempts') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-danger">
                    <span class="info-box-icon"><i class="fas fa-lock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Blocked Entries') }}</span>
                        <span class="info-box-number">{{ $logs->filter(fn($l) => $l->isBlocked())->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-info">
                    <span class="info-box-icon"><i class="fas fa-envelope"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Unique Emails') }}</span>
                        <span class="info-box-number">{{ $logs->unique('email')->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-secondary">
                    <span class="info-box-icon"><i class="fas fa-network-wired"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Unique IPs') }}</span>
                        <span class="info-box-number">{{ $logs->unique('ip_address')->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</section>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fas fa-trash"></i> {{ __('Clear Old Logs') }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="clearLogsForm">
                <div class="modal-body">
                    <p>{{ __('Delete security logs older than:') }}</p>
                    
                    <div class="form-group">
                        <label for="days">{{ __('Number of Days') }} <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="days" 
                               name="days" 
                               min="1" 
                               max="365" 
                               value="90" 
                               required>
                        <small class="form-text text-muted">
                            {{ __('Logs older than this many days will be permanently deleted') }}
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> {{ __('Delete Logs') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Clear Logs Button
    $('#clearLogsBtn').on('click', function() {
        $('#clearLogsModal').modal('show');
    });

    // Clear Logs Form Submit
    $('#clearLogsForm').on('submit', function(e) {
        e.preventDefault();
        
        var days = $('#days').val();

        Swal.fire({
            title: '{{ __("Are you sure?") }}',
            html: '{{ __("This will permanently delete all logs older than") }} <strong>' + days + ' {{ __("days") }}</strong>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash"></i> {{ __("Yes, Delete") }}',
            cancelButtonText: '<i class="fas fa-times"></i> {{ __("Cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: '{{ __("Processing...") }}',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: '{{ route("admin.security.logs.clear") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        days: days
                    },
                    success: function(response) {
                        $('#clearLogsModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("Success") }}',
                            text: response.message,
                            timer: 3000
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("Error") }}',
                            text: xhr.responseJSON?.message || '{{ __("Failed to clear logs") }}'
                        });
                    }
                });
            }
        });
    });

    // Quick filter by clicking on badges
    $(document).on('click', '.badge', function(e) {
        var text = $(this).text().trim();
        if (text === 'Admin' || text === 'Student') {
            $('#user_type').val(text.toLowerCase());
            $('#filterForm').submit();
        }
    });
});
</script>
@endsection

@section('styles')
<style>
    .table-danger {
        background-color: #f8d7da !important;
    }
    .text-break {
        word-break: break-all;
    }
    .info-box {
        border-radius: 6px;
    }
</style>
@endsection
