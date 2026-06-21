@extends('admin.layouts.master')
@section('title', __('IP Whitelist Management'))
@section('content')

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-network-wired text-primary"></i> {{ __('IP Whitelist Management') }}</h3>
                        <p class="text-muted mb-0">{{ __('Control admin access by IP address') }}</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success" id="addIpBtn">
                            <i class="fas fa-plus"></i> {{ __('Add IP Address') }}
                        </button>
                        <a href="{{ route('admin.security.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ __('Note:') }}</strong> 
                    {{ __('When IP whitelisting is enabled in security settings, only IPs listed here will be able to access admin functions.') }}
                    {{ __('Your current IP:') }} <code>{{ request()->ip() }}</code>
                </div>
            </div>
        </div>

        <!-- IP Whitelist Table -->
        <div class="row">
            <div class="col-12">
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i> {{ __('Whitelisted IP Addresses') }}
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-info">{{ $whitelists->count() }} {{ __('IPs') }}</span>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('IP Address') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Created By') }}</th>
                                    <th>{{ __('Created At') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($whitelists as $whitelist)
                                <tr id="whitelist-row-{{ $whitelist->id }}">
                                    <td>
                                        <code class="text-lg">{{ $whitelist->ip_address }}</code>
                                    </td>
                                    <td>
                                        {{ $whitelist->description ?: '-' }}
                                    </td>
                                    <td>
                                        @if($whitelist->is_active)
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> {{ __('Active') }}
                                        </span>
                                        @else
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-times-circle"></i> {{ __('Inactive') }}
                                        </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($whitelist->creator)
                                        {{ $whitelist->creator->first_name }} {{ $whitelist->creator->last_name }}
                                        @else
                                        <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $whitelist->created_at->format('M d, Y H:i') }}</small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-{{ $whitelist->is_active ? 'warning' : 'success' }} toggle-btn" 
                                                    data-id="{{ $whitelist->id }}"
                                                    data-status="{{ $whitelist->is_active ? 'active' : 'inactive' }}">
                                                <i class="fas fa-toggle-{{ $whitelist->is_active ? 'on' : 'off' }}"></i> 
                                                {{ $whitelist->is_active ? __('Deactivate') : __('Activate') }}
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger delete-btn" 
                                                    data-id="{{ $whitelist->id }}"
                                                    data-ip="{{ $whitelist->ip_address }}">
                                                <i class="fas fa-trash"></i> {{ __('Delete') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-network-wired fa-3x mb-2"></i>
                                        <p>{{ __('No IP addresses whitelisted yet') }}</p>
                                        <button type="button" class="btn btn-success" onclick="$('#addIpBtn').click()">
                                            <i class="fas fa-plus"></i> {{ __('Add Your First IP') }}
                                        </button>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Add IP Modal -->
<div class="modal fade" id="addIpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title text-white">
                    <i class="fas fa-plus"></i> {{ __('Add IP to Whitelist') }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="addIpForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ip_address">{{ __('IP Address') }} <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="ip_address" 
                               name="ip_address" 
                               required
                               placeholder="192.168.1.1"
                               pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        <small class="form-text text-muted">
                            {{ __('Enter a valid IPv4 address') }}
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="description">{{ __('Description') }}</label>
                        <input type="text" 
                               class="form-control" 
                               id="description" 
                               name="description" 
                               placeholder="{{ __('e.g., Office Main Network, Home Router, etc.') }}">
                        <small class="form-text text-muted">
                            {{ __('Optional: Add a description to identify this IP') }}
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i>
                        <strong>{{ __('Tip:') }}</strong> {{ __('Your current IP is') }} 
                        <code id="currentIp">{{ request()->ip() }}</code>
                        <button type="button" class="btn btn-sm btn-info ml-2" id="useCurrentIpBtn">
                            {{ __('Use This IP') }}
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> {{ __('Add IP') }}
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
    // Add IP Button
    $('#addIpBtn').on('click', function() {
        $('#ip_address').val('');
        $('#description').val('');
        $('#addIpModal').modal('show');
    });

    // Use Current IP Button
    $('#useCurrentIpBtn').on('click', function() {
        var currentIp = $('#currentIp').text();
        $('#ip_address').val(currentIp);
    });

    // Add IP Form Submit
    $('#addIpForm').on('submit', function(e) {
        e.preventDefault();
        
        var ipAddress = $('#ip_address').val();
        var description = $('#description').val();

        // Show loading
        Swal.fire({
            title: '{{ __("Processing...") }}',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '{{ route("admin.security.ip-whitelist.store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ip_address: ipAddress,
                description: description
            },
            success: function(response) {
                $('#addIpModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: '{{ __("Success") }}',
                    text: response.message,
                    timer: 2000
                }).then(() => {
                    location.reload();
                });
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("Error") }}',
                    text: xhr.responseJSON?.message || '{{ __("Failed to add IP address") }}'
                });
            }
        });
    });

    // Toggle Status Button
    $(document).on('click', '.toggle-btn', function() {
        var id = $(this).data('id');
        var status = $(this).data('status');
        var action = status === 'active' ? '{{ __("deactivate") }}' : '{{ __("activate") }}';

        Swal.fire({
            title: '{{ __("Confirm Action") }}',
            text: '{{ __("Do you want to") }} ' + action + ' {{ __("this IP address?") }}',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> {{ __("Yes") }}',
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
                    url: '{{ route("admin.security.ip-whitelist.toggle", ":id") }}'.replace(':id', id),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("Success") }}',
                            text: response.message,
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("Error") }}',
                            text: xhr.responseJSON?.message || '{{ __("Failed to toggle status") }}'
                        });
                    }
                });
            }
        });
    });

    // Delete Button
    $(document).on('click', '.delete-btn', function() {
        var id = $(this).data('id');
        var ip = $(this).data('ip');

        Swal.fire({
            title: '{{ __("Delete IP Whitelist") }}',
            html: '{{ __("Are you sure you want to remove") }} <code>' + ip + '</code> {{ __("from the whitelist?") }}',
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
                    url: '{{ route("admin.security.ip-whitelist.delete", ":id") }}'.replace(':id', id),
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: '{{ __("Success") }}',
                            text: response.message,
                            timer: 2000
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("Error") }}',
                            text: xhr.responseJSON?.message || '{{ __("Failed to delete IP") }}'
                        });
                    }
                });
            }
        });
    });
});
</script>
@endsection

@section('styles')
<style>
    .text-lg {
        font-size: 1.1rem;
    }
</style>
@endsection
