@extends('admin.layouts.master')
@section('title', __('Blocked IPs Management'))

@section('page_css')
<style>
    

    
    .form-inline-custom {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .form-group-custom {
        flex: 1;
        min-width: 200px;
    }
    
    .form-group-custom label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #555;
        font-size: 13px;
    }
    
    .form-control-custom {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-control-custom:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    

    

    
    .status-badge {
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    
    .status-blocked {
        background: #dc3545;
        color: white;
    }
    
    .status-warning {
        background: #ffc107;
        color: #333;
    }
    
    .status-expired {
        background: #6c757d;
        color: white;
    }
    
    .status-clear {
        background: #e9ecef;
        color: #6c757d;
    }
    
    .ip-code {
        font-family: 'Courier New', monospace;
        background: #f8f9fa;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
        color: #495057;
    }
    
    .block-reason {
        font-size: 11px;
        color: #6c757d;
        font-style: italic;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 48px;
        opacity: 0.3;
        margin-bottom: 15px;
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-ban text-danger"></i> {{ __('Blocked IPs Management') }}</h3>
                        <p class="text-muted mb-0">{{ __('View and manage IP addresses blocked by DDoS protection and failed login attempts') }}</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.security.whitelist') }}" class="btn btn-success">
                            <i class="fas fa-shield-alt"></i> {{ __('IP Whitelist') }}
                        </a>
                        <a href="{{ route('admin.security.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4 col-sm-6">
                <div class="card" style="background: linear-gradient(135deg, #f56954 0%, #e74c3c 100%); color: white; border: none; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3); border-radius: 10px;">
                    <div class="card-block text-center" style="padding: 25px 20px;">
                        <div style="font-size: 48px; font-weight: bold; margin-bottom: 10px;">
                            {{ $failedAttempts->where('blocked_until', '>', now())->count() }}
                        </div>
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 5px;">
                            {{ __('Currently Blocked') }}
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">
                            <i class="fas fa-ban"></i> {{ __('Active Blocks') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; border: none; box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3); border-radius: 10px;">
                    <div class="card-block text-center" style="padding: 25px 20px;">
                        <div style="font-size: 48px; font-weight: bold; margin-bottom: 10px;">
                            {{ $failedAttempts->where('attempts', '>', 0)->count() }}
                        </div>
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 5px;">
                            {{ __('Failed Attempts') }}
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">
                            <i class="fas fa-exclamation-triangle"></i> {{ __('Security Warnings') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-12">
                <div class="card" style="background: linear-gradient(135deg, #00c0ef 0%, #3498db 100%); color: white; border: none; box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3); border-radius: 10px;">
                    <div class="card-block text-center" style="padding: 25px 20px;">
                        <div style="font-size: 48px; font-weight: bold; margin-bottom: 10px;">
                            {{ $failedAttempts->where('blocked_until', '<=', now())->where('blocked_until', '!=', null)->count() }}
                        </div>
                        <div style="font-size: 16px; font-weight: 600; margin-bottom: 5px;">
                            {{ __('Expired Blocks') }}
                        </div>
                        <div style="font-size: 13px; opacity: 0.9;">
                            <i class="fas fa-clock"></i> {{ __('Historical Data') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Block Form -->
        <div class="row">
            <div class="col-12">
                <div class="card card-danger card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus-circle"></i> {{ __('Block IP Address Manually') }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <form id="blockIpForm" class="form-inline-custom">
                            @csrf
                            <div class="form-group-custom">
                                <label for="ip_address">{{ __('IP Address') }}</label>
                                <input type="text" id="ip_address" name="ip_address" class="form-control-custom" placeholder="e.g., 192.168.1.1" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" required>
                            </div>
                            <div class="form-group-custom">
                                <label for="duration">{{ __('Block Duration') }}</label>
                                <select id="duration" name="duration" class="form-control-custom" required>
                                    <option value="15">15 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="60">1 hour</option>
                                    <option value="360">6 hours</option>
                                    <option value="1440">24 hours</option>
                                    <option value="10080">7 days</option>
                                </select>
                            </div>
                            <div class="form-group-custom" style="flex: 2;">
                                <label for="reason">{{ __('Reason (Optional)') }}</label>
                                <input type="text" id="reason" name="reason" class="form-control-custom" placeholder="e.g., Suspicious activity detected" maxlength="500">
                            </div>
                            <div>
                                <label style="visibility: hidden;">{{ __('Action') }}</label>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-ban"></i> {{ __('Block IP') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Blocked IPs Table -->
        <div class="row">
            <div class="col-12">
                <div id="blocked-table" class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i> {{ __('Blocked IP Addresses & Failed Attempts') }}
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-danger">{{ $failedAttempts->total() }} {{ __('records') }}</span>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('IP Address') }}</th>
                                    <th>{{ __('User/Email') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Attempts') }}</th>
                                    <th>{{ __('Last Attempt') }}</th>
                                    <th>{{ __('Blocked Until') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Reason') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($failedAttempts as $attempt)
                                <tr>
                                    <td>
                                        <code class="ip-code">{{ $attempt->ip_address }}</code>
                                    </td>
                                    <td>{{ Str::limit($attempt->email, 30) }}</td>
                                    <td>
                                        @if($attempt->user_type === 'admin')
                                            <span class="badge badge-primary">Admin</span>
                                        @elseif($attempt->user_type === 'student')
                                            <span class="badge badge-info">Student</span>
                                        @elseif($attempt->user_type === 'applicant')
                                            <span class="badge badge-warning">Applicant</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($attempt->user_type) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-danger">{{ $attempt->attempts }}</span>
                                    </td>
                                    <td>
                                        @if($attempt->last_attempt_at)
                                            {{ $attempt->last_attempt_at->diffForHumans() }}
                                            <br><small class="text-muted">{{ $attempt->last_attempt_at->format('M d, H:i') }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($attempt->blocked_until)
                                            @if($attempt->blocked_until > now())
                                                {{ $attempt->blocked_until->diffForHumans() }}
                                                <br><small class="text-muted">{{ $attempt->blocked_until->format('M d, H:i') }}</small>
                                            @else
                                                <span class="text-muted">Expired</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($attempt->is_cache_blocked) && $attempt->is_cache_blocked)
                                            <span class="status-badge status-blocked">Cache Blocked</span>
                                        @elseif($attempt->blocked_until && $attempt->blocked_until > now())
                                            <span class="status-badge status-blocked">DB Blocked</span>
                                        @elseif($attempt->blocked_until)
                                            <span class="status-badge status-expired">Expired</span>
                                        @elseif($attempt->attempts > 0)
                                            <span class="status-badge status-warning">Warning</span>
                                        @else
                                            <span class="status-badge status-clear">Clear</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="block-reason" title="{{ $attempt->user_agent }}">
                                            {{ Str::limit($attempt->user_agent, 30) }}
                                        </small>
                                    </td>
                                    <td>
                                        @if((isset($attempt->is_cache_blocked) && $attempt->is_cache_blocked) || $attempt->attempts > 0 || $attempt->blocked_until)
                                            <button class="btn btn-success btn-sm btn-unblock-ip" data-ip="{{ $attempt->ip_address }}">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="fas fa-shield-alt"></i>
                                            <p>{{ __('No blocked IPs or failed attempts found') }}</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($failedAttempts->hasPages())
                    <div class="card-footer clearfix">
                        {{ $failedAttempts->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        @if($failedAttempts->total() > 0)
        <div class="row mt-3">
            <div class="col-12">
                <button class="btn btn-danger" id="btnClearAllBlocks">
                    <i class="fas fa-trash-alt"></i> {{ __('Clear All Blocks') }}
                </button>
            </div>
        </div>
        @endif
        
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('scripts')
<script type="text/javascript">
    // Wait for DOM to load
    document.addEventListener('DOMContentLoaded', function() {
        
        // Unblock IP buttons - Event delegation
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-unblock-ip')) {
                const button = e.target.closest('.btn-unblock-ip');
                const ip = button.getAttribute('data-ip');
                
                if (!confirm('Are you sure you want to unblock IP address: ' + ip + '?')) {
                    return;
                }

                fetch('{{ route("admin.security.blocked-ips.unblock") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        ip_address: ip
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to unblock IP'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred: ' + error.message);
                });
            }
        });

        // Clear all blocks button
        const btnClearAll = document.getElementById('btnClearAllBlocks');
        if (btnClearAll) {
            btnClearAll.addEventListener('click', function() {
                if (!confirm('Are you sure you want to clear ALL blocked IP addresses? This action cannot be undone.')) {
                    return;
                }

                if (!confirm('This will reset all failed login attempts and unblock all IPs. Continue?')) {
                    return;
                }

                fetch('{{ route("admin.security.blocked-ips.clear-all") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to clear blocks'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred: ' + error.message);
                });
            });
        }

        // Block IP form submission
        const blockForm = document.getElementById('blockIpForm');
        if (blockForm) {
            blockForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const data = {
                    ip_address: formData.get('ip_address'),
                    duration: parseInt(formData.get('duration')),
                    reason: formData.get('reason') || 'Manually blocked by admin'
                };

                if (!confirm('Block IP address ' + data.ip_address + ' for ' + formData.get('duration') + ' minutes?')) {
                    return;
                }

                fetch('{{ route("admin.security.blocked-ips.block") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to block IP'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred: ' + error.message);
                });
            });
        } else {
            console.error('Block IP form not found');
        }
    });
</script>
@endsection
