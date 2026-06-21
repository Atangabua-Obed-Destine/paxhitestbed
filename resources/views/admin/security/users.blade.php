@extends('admin.layouts.master')
@section('title', __('User Management'))
@section('content')

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-users-cog text-primary"></i> {{ __('User Management') }}</h3>
                        <p class="text-muted mb-0">{{ __('Block, unblock, and manage user accounts') }}</p>
                    </div>
                    <div>
                        <a href="{{ route('admin.security.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Type Filter -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="btn-group" role="group">
                    <a href="{{ route('admin.security.users', ['type' => 'users']) }}" 
                       class="btn btn-{{ $userType === 'users' ? 'primary' : 'outline-primary' }}">
                        <i class="fas fa-user-tie"></i> {{ __('Staff/Admin Users') }}
                    </a>
                    <a href="{{ route('admin.security.users', ['type' => 'students']) }}" 
                       class="btn btn-{{ $userType === 'students' ? 'primary' : 'outline-primary' }}">
                        <i class="fas fa-user-graduate"></i> {{ __('Students') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="row">
            <div class="col-12">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i> 
                            {{ $userType === 'students' ? __('Student Accounts') : __('Staff/Admin Accounts') }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <table id="usersTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Online Status') }}</th>
                                    <th>{{ __('Failed Attempts') }}</th>
                                    <th>{{ __('2FA') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Blocked Info') }}</th>
                                    <th width="250">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                <tr id="user-row-{{ $user->id }}" class="{{ $user->isBlocked() ? 'table-danger' : '' }}">
                                    <td>{{ $userType === 'students' ? $user->student_id : $user->staff_id }}</td>
                                    <td>
                                        <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @php
                                            $isOnline = $user->last_seen_at && $user->last_seen_at->diffInMinutes(now()) < 5;
                                        @endphp
                                        @if($isOnline)
                                            <span class="badge badge-success" style="font-size: 12px;">
                                                <i class="fas fa-circle" style="font-size: 8px;"></i> {{ __('Online') }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary" style="font-size: 12px;">
                                                <i class="far fa-circle" style="font-size: 8px;"></i> {{ __('Offline') }}
                                            </span>
                                        @endif
                                        <br>
                                        <small class="text-muted">
                                            @if($user->last_seen_at)
                                                {{ $user->last_seen_at->diffForHumans() }}
                                            @else
                                                {{ __('Never') }}
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $user->failed_login_attempts > 0 ? 'danger' : 'success' }}">
                                            {{ $user->failed_login_attempts }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" 
                                                   class="custom-control-input toggle-2fa" 
                                                   id="2fa-{{ $user->id }}"
                                                   data-id="{{ $user->id }}"
                                                   data-type="{{ $userType === 'students' ? 'student' : 'user' }}"
                                                   {{ $user->two_factor_enabled ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="2fa-{{ $user->id }}">
                                                {{ $user->two_factor_enabled ? __('Enabled') : __('Disabled') }}
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        @if($user->isBlocked())
                                        <span class="badge badge-danger">
                                            <i class="fas fa-lock"></i> {{ __('Blocked') }}
                                        </span>
                                        @else
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> {{ __('Active') }}
                                        </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->isBlocked())
                                        <small>
                                            <strong>{{ __('Blocked At:') }}</strong> {{ $user->blocked_at->format('M d, Y H:i') }}<br>
                                            <strong>{{ __('Reason:') }}</strong> {{ $user->block_reason }}<br>
                                            @if($user->blocker)
                                            <strong>{{ __('By:') }}</strong> {{ $user->blocker->first_name }} {{ $user->blocker->last_name }}
                                            @endif
                                        </small>
                                        @else
                                        <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            @if($user->isBlocked())
                                            <button type="button" 
                                                    class="btn btn-sm btn-success unblock-btn" 
                                                    data-user-id="{{ $user->id }}"
                                                    data-user-type="{{ $userType === 'students' ? 'student' : 'user' }}"
                                                    data-user-name="{{ $user->first_name }} {{ $user->last_name }}">
                                                <i class="fas fa-unlock"></i> {{ __('Unblock') }}
                                            </button>
                                            @else
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger block-btn" 
                                                    data-user-id="{{ $user->id }}"
                                                    data-user-type="{{ $userType === 'students' ? 'student' : 'user' }}"
                                                    data-user-name="{{ $user->first_name }} {{ $user->last_name }}">
                                                <i class="fas fa-lock"></i> {{ __('Block') }}
                                            </button>
                                            @endif
                                            
                                            @if($user->failed_login_attempts > 0)
                                            <button type="button" 
                                                    class="btn btn-sm btn-warning reset-btn" 
                                                    data-user-id="{{ $user->id }}"
                                                    data-user-type="{{ $userType === 'students' ? 'student' : 'user' }}"
                                                    data-user-name="{{ $user->first_name }} {{ $user->last_name }}">
                                                <i class="fas fa-redo"></i> {{ __('Reset') }}
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer clearfix">
                        {{ $users->appends(['type' => $userType])->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Failed Attempts for Non-Existent Users -->
        @if(isset($orphanedAttempts) && $orphanedAttempts->count() > 0)
        <div class="row">
            <div class="col-12">
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> 
                            {{ __('Failed Login Attempts - Unregistered Emails') }}
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            {{ __('These emails have failed login attempts but do not exist in the system. They might be typos or unauthorized access attempts.') }}
                        </div>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Failed Attempts') }}</th>
                                    <th>{{ __('Last Attempt') }}</th>
                                    <th>{{ __('IP Address') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th width="150">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orphanedAttempts as $attempt)
                                <tr class="{{ $attempt->isBlocked() ? 'table-danger' : '' }}">
                                    <td><strong>{{ $attempt->email }}</strong></td>
                                    <td>
                                        <span class="badge badge-danger">
                                            {{ $attempt->attempts }}
                                        </span>
                                    </td>
                                    <td>{{ $attempt->last_attempt_at->format('M d, Y H:i:s') }}</td>
                                    <td><code>{{ $attempt->ip_address }}</code></td>
                                    <td>
                                        @if($attempt->isBlocked())
                                        <span class="badge badge-danger">
                                            <i class="fas fa-ban"></i> {{ __('Blocked') }}
                                            <br><small>{{ __('Until:') }} {{ $attempt->blocked_until->format('M d, H:i') }}</small>
                                        </span>
                                        @else
                                        <span class="badge badge-warning">
                                            <i class="fas fa-exclamation-triangle"></i> {{ __('Active') }}
                                        </span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-warning reset-orphaned-btn" 
                                                data-email="{{ $attempt->email }}"
                                                data-user-type="{{ $attempt->user_type }}">
                                            <i class="fas fa-redo"></i> {{ __('Reset') }}
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</section>

<!-- Block User Modal -->
<div class="modal fade" id="blockUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fas fa-lock"></i> {{ __('Block User') }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="blockUserForm">
                <div class="modal-body">
                    <p>{{ __('Are you sure you want to block this user?') }}</p>
                    <p><strong id="blockUserName"></strong></p>
                    
                    <div class="form-group">
                        <label for="blockReason">{{ __('Block Reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" 
                                  id="blockReason" 
                                  name="block_reason" 
                                  rows="3" 
                                  required
                                  placeholder="{{ __('Enter the reason for blocking this user...') }}"></textarea>
                    </div>

                    <input type="hidden" id="blockUserId" name="user_id">
                    <input type="hidden" id="blockUserType" name="user_type">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-lock"></i> {{ __('Block User') }}
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
    // Initialize DataTable
    $('#usersTable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "ordering": true,
        "searching": true,
        "pageLength": 20,
        "language": {
            "emptyTable": "{{ __('No users found') }}"
        }
    });

    // Block User Button
    $(document).on('click', '.block-btn', function() {
        var userId = $(this).data('user-id');
        var userType = $(this).data('user-type');
        var userName = $(this).data('user-name');
        
        $('#blockUserId').val(userId);
        $('#blockUserType').val(userType);
        $('#blockUserName').text(userName);
        $('#blockReason').val('');
        
        $('#blockUserModal').modal('show');
    });

    // Block User Form Submit
    $('#blockUserForm').on('submit', function(e) {
        e.preventDefault();
        
        var userId = $('#blockUserId').val();
        var userType = $('#blockUserType').val();
        var blockReason = $('#blockReason').val();

        if (!blockReason.trim()) {
            alert('{{ __("Please enter a block reason") }}');
            return;
        }

        $.ajax({
            url: '{{ route("admin.security.users.block") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                user_id: userId,
                user_type: userType,
                block_reason: blockReason
            },
            success: function(response) {
                $('#blockUserModal').modal('hide');
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || '{{ __("Failed to block user") }}');
            }
        });
    });

    // Unblock User Button
    $(document).on('click', '.unblock-btn', function() {
        var userId = $(this).data('user-id');
        var userType = $(this).data('user-type');
        var userName = $(this).data('user-name');

        if (confirm('{{ __("Are you sure you want to unblock") }} ' + userName + '?')) {
            $.ajax({
                url: '{{ route("admin.security.users.unblock") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    user_id: userId,
                    user_type: userType
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || '{{ __("Failed to unblock user") }}');
                }
            });
        }
    });

    // Reset Attempts Button
    $(document).on('click', '.reset-btn', function() {
        var userId = $(this).data('user-id');
        var userType = $(this).data('user-type');
        var userName = $(this).data('user-name');

        if (confirm('{{ __("Reset failed login attempts for") }} ' + userName + '?')) {
            $.ajax({
                url: '{{ route("admin.security.users.reset-attempts") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    user_id: userId,
                    user_type: userType
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || '{{ __("Failed to reset attempts") }}');
                }
            });
        }
    });

    // Toggle 2FA
    $(document).on('change', '.toggle-2fa', function() {
        var checkbox = $(this);
        var userId = checkbox.data('id');
        var userType = checkbox.data('type');
        var enabled = checkbox.is(':checked');
        
        $.ajax({
            url: '{{ route("admin.security.users.toggle-2fa") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                user_id: userId,
                user_type: userType,
                enabled: enabled ? 1 : 0
            },
            success: function(response) {
                alert(response.message);
                // Update label
                checkbox.next('label').text(enabled ? '{{ __("Enabled") }}' : '{{ __("Disabled") }}');
            },
            error: function(xhr) {
                // Revert checkbox
                checkbox.prop('checked', !enabled);
                alert('{{ __("Failed to update 2FA status") }}');
            }
        });
    });

    // Reset Orphaned Attempts Button
    $(document).on('click', '.reset-orphaned-btn', function() {
        var email = $(this).data('email');
        var userType = $(this).data('user-type');

        if (confirm('{{ __("Reset failed login attempts for") }} ' + email + '?')) {
            $.ajax({
                url: '{{ route("admin.security.users.reset-orphaned") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    email: email,
                    user_type: userType
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || '{{ __("Failed to reset attempts") }}');
                }
            });
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
    .btn-group .btn {
        margin-right: 2px;
    }
    #usersTable_wrapper .row:first-child {
        margin-bottom: 15px;
    }
</style>
@endsection
