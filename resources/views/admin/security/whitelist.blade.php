@extends('admin.layouts.master')

@section('page_css')
<style>
    .gradient-card-green {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 6px rgba(40, 167, 69, 0.3);
    }
    
    .gradient-card-blue {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 123, 255, 0.3);
    }
    
    .gradient-card-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 6px rgba(23, 162, 184, 0.3);
    }
    
    .stat-card {
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card h3 {
        font-size: 2.5rem;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .stat-card p {
        margin: 0;
        font-size: 1rem;
        opacity: 0.9;
    }
    
    .stat-card .icon {
        font-size: 3rem;
        opacity: 0.3;
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .whitelist-form {
        background: #fff;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .whitelist-table {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .table thead th {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        padding: 15px;
    }
    
    .table tbody td {
        padding: 15px;
        vertical-align: middle;
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-block;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .ip-badge {
        font-family: 'Courier New', monospace;
        background: #e9ecef;
        padding: 5px 10px;
        border-radius: 5px;
        font-weight: 600;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    
    .btn-toggle-active {
        background: #28a745;
        color: white;
    }
    
    .btn-toggle-inactive {
        background: #6c757d;
        color: white;
    }
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">{{ __('IP Whitelist Management') }}</h2>
                <p class="text-muted">{{ __('Manage IPs that are allowed to bypass all security checks') }}</p>
            </div>
            <div>
                <a href="{{ route('admin.security.blocked-ips') }}" class="btn btn-outline-primary">
                    <i class="fas fa-ban"></i> {{ __('Blocked IPs') }}
                </a>
                <a href="{{ route('admin.security.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card gradient-card-green position-relative">
                    <p>{{ __('Active Whitelisted IPs') }}</p>
                    <h3>{{ $whitelistedIps->where('is_active', true)->count() }}</h3>
                    <i class="fas fa-shield-alt icon"></i>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card gradient-card-blue position-relative">
                    <p>{{ __('Total Whitelist Entries') }}</p>
                    <h3>{{ $whitelistedIps->count() }}</h3>
                    <i class="fas fa-list icon"></i>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card gradient-card-info position-relative">
                    <p>{{ __('Inactive Entries') }}</p>
                    <h3>{{ $whitelistedIps->where('is_active', false)->count() }}</h3>
                    <i class="fas fa-pause-circle icon"></i>
                </div>
            </div>
        </div>

        <!-- Add to Whitelist Form -->
        <div class="whitelist-form">
            <h4 class="mb-3">
                <i class="fas fa-plus-circle text-success"></i> {{ __('Add IP to Whitelist') }}
            </h4>
            <form id="addWhitelistForm">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="ip_address">{{ __('IP Address') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" 
                                   placeholder="e.g., 192.168.1.100" required>
                            <small class="form-text text-muted">{{ __('Enter a valid IPv4 or IPv6 address') }}</small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="description">{{ __('Description') }}</label>
                            <input type="text" class="form-control" id="description" name="description" 
                                   placeholder="e.g., Office network, Admin workstation">
                            <small class="form-text text-muted">{{ __('Optional: Describe this IP address') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-plus"></i> {{ __('Add to Whitelist') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Whitelist Table -->
        <div class="whitelist-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('IP Address') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Added By') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th class="text-center">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($whitelistedIps as $whitelist)
                        <tr id="whitelist-row-{{ $whitelist->id }}">
                            <td>
                                <span class="ip-badge">{{ $whitelist->ip_address }}</span>
                            </td>
                            <td>
                                <small>{{ $whitelist->description ?? '—' }}</small>
                            </td>
                            <td>
                                @if($whitelist->is_active)
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i> Active
                                    </span>
                                @else
                                    <span class="status-badge status-inactive">
                                        <i class="fas fa-times-circle"></i> Inactive
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($whitelist->creator)
                                    <small>{{ $whitelist->creator->name }}</small>
                                @else
                                    <small class="text-muted">—</small>
                                @endif
                            </td>
                            <td>
                                <small>{{ $whitelist->created_at->format('M d, Y H:i') }}</small>
                                <br><small class="text-muted">{{ $whitelist->created_at->diffForHumans() }}</small>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-toggle-whitelist {{ $whitelist->is_active ? 'btn-toggle-active' : 'btn-toggle-inactive' }}" 
                                        data-id="{{ $whitelist->id }}"
                                        title="{{ $whitelist->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-remove-whitelist" 
                                        data-id="{{ $whitelist->id }}"
                                        data-ip="{{ $whitelist->ip_address }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>{{ __('No whitelisted IPs found. Add an IP address to get started.') }}</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Box -->
        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle"></i>
            <strong>{{ __('About IP Whitelist:') }}</strong>
            {{ __('Whitelisted IPs will bypass all security checks including DDoS protection, rate limiting, and failed login attempt tracking. Use this feature carefully to avoid security vulnerabilities.') }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Add to whitelist form submission
    document.getElementById('addWhitelistForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        fetch('{{ route("admin.security.whitelist.add") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Failed to add IP to whitelist');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding IP to whitelist');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
    
    // Toggle whitelist status
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-toggle-whitelist')) {
            const btn = e.target.closest('.btn-toggle-whitelist');
            const id = btn.dataset.id;
            
            if (confirm('Are you sure you want to toggle the status of this IP?')) {
                fetch('{{ route("admin.security.whitelist.toggle") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to toggle whitelist status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while toggling whitelist status');
                });
            }
        }
    });
    
    // Remove from whitelist
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-whitelist')) {
            const btn = e.target.closest('.btn-remove-whitelist');
            const id = btn.dataset.id;
            const ip = btn.dataset.ip;
            
            if (confirm(`Are you sure you want to remove ${ip} from the whitelist? This IP will be subject to all security checks again.`)) {
                fetch('{{ route("admin.security.whitelist.remove") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        document.getElementById('whitelist-row-' + id).remove();
                        
                        // Check if table is empty and show empty state
                        const tbody = document.querySelector('.whitelist-table tbody');
                        if (tbody.querySelectorAll('tr').length === 0) {
                            location.reload();
                        }
                    } else {
                        alert(data.message || 'Failed to remove IP from whitelist');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while removing IP from whitelist');
                });
            }
        }
    });
});
</script>
@endsection
