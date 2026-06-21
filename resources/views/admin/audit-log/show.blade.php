@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
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
                            <li class="breadcrumb-item"><a href="{{ route('admin.audit-log.index') }}">{{ trans_choice('module_audit_trail', 2) }}</a></li>
                            <li class="breadcrumb-item active">{{ __('view') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ trans_choice('module_audit_trail', 1) }} #{{ $row->id }}</h5>
                        <div class="card-header-right">
                            <a href="{{ route('admin.audit-log.index') }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Event Summary Alert -->
                        <div class="alert alert-{{ $row->event == 'created' ? 'success' : ($row->event == 'updated' ? 'info' : ($row->event == 'deleted' ? 'danger' : 'primary')) }} mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="alert-heading mb-2">
                                        @if($row->event == 'created')
                                            <i class="fas fa-plus-circle"></i> {{ __('record_created') }}
                                        @elseif($row->event == 'updated')
                                            <i class="fas fa-edit"></i> {{ __('record_updated') }}
                                        @elseif($row->event == 'deleted')
                                            <i class="fas fa-trash"></i> {{ __('record_deleted') }}
                                        @elseif(in_array($row->event, ['logged_in', 'login']))
                                            <i class="fas fa-sign-in-alt"></i> {{ __('user_logged_in') }}
                                        @elseif(in_array($row->event, ['logged_out', 'logout']))
                                            <i class="fas fa-sign-out-alt"></i> {{ __('user_logged_out') }}
                                        @elseif($row->event == 'failed_login')
                                            <i class="fas fa-exclamation-triangle"></i> {{ __('failed_login_attempt') }}
                                        @else
                                            <i class="fas fa-info-circle"></i> {{ $row->event_name }}
                                        @endif
                                    </h5>
                                    <p class="mb-0"><strong>{{ $row->description }}</strong></p>
                                </div>
                                <div class="col-md-4 text-right">
                                    <h6 class="mb-1">{{ $row->created_at->format('M d, Y') }}</h6>
                                    <p class="mb-0">{{ $row->created_at->format('h:i:s A') }}</p>
                                    <small class="text-muted">{{ $row->created_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light mb-3 border">
                                    <div class="card-header bg-white">
                                        <h6 class="mb-0"><i class="fas fa-info-circle text-primary"></i> <strong>{{ __('event_details') }}</strong></h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="40%" class="text-muted"><i class="fas fa-hashtag"></i> {{ __('field_id') }}:</th>
                                                <td><strong>#{{ $row->id }}</strong></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted"><i class="fas fa-bolt"></i> {{ __('field_event') }}:</th>
                                                <td>
                                                    @php
                                                        $badgeClass = 'badge-secondary';
                                                        $icon = 'fa-circle';
                                                        
                                                        if($row->event == 'created') {
                                                            $badgeClass = 'badge-success';
                                                            $icon = 'fa-plus-circle';
                                                        }
                                                        elseif($row->event == 'updated') {
                                                            $badgeClass = 'badge-info';
                                                            $icon = 'fa-edit';
                                                        }
                                                        elseif($row->event == 'deleted') {
                                                            $badgeClass = 'badge-danger';
                                                            $icon = 'fa-trash';
                                                        }
                                                        elseif(in_array($row->event, ['logged_in', 'login'])) {
                                                            $badgeClass = 'badge-primary';
                                                            $icon = 'fa-sign-in-alt';
                                                        }
                                                        elseif(in_array($row->event, ['logged_out', 'logout'])) {
                                                            $badgeClass = 'badge-warning';
                                                            $icon = 'fa-sign-out-alt';
                                                        }
                                                        elseif($row->event == 'failed_login') {
                                                            $badgeClass = 'badge-danger';
                                                            $icon = 'fa-exclamation-triangle';
                                                        }
                                                    @endphp
                                                    <span class="badge {{ $badgeClass }} px-3 py-2">
                                                        <i class="fas {{ $icon }}"></i> {{ $row->event_name }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted"><i class="fas fa-database"></i> {{ __('field_model') }}:</th>
                                                <td>
                                                    @php
                                                        $financialModels = ['Transaction', 'Payroll', 'JournalEntry', 'Fee', 'Income', 'Expense', 'ChartOfAccount', 'FiscalYear'];
                                                        $academicModels = ['Faculty', 'Program', 'Session', 'Semester', 'Subject', 'Batch', 'ClassRoutine'];
                                                        $studentModels = ['Student', 'StudentEnroll', 'EnrollSubject'];
                                                        
                                                        $badgeColor = 'secondary';
                                                        if(in_array($row->model_name, $financialModels)) $badgeColor = 'success';
                                                        elseif(in_array($row->model_name, $academicModels)) $badgeColor = 'primary';
                                                        elseif(in_array($row->model_name, $studentModels)) $badgeColor = 'info';
                                                    @endphp
                                                    <span class="badge badge-{{ $badgeColor }} px-3 py-2">{{ $row->model_name }}</span>
                                                    @if($row->auditable_id)
                                                    <br><small class="text-muted mt-1 d-inline-block">Record ID: #{{ $row->auditable_id }}</small>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted"><i class="far fa-calendar-alt"></i> {{ __('field_date_time') }}:</th>
                                                <td>
                                                    <strong>{{ $row->created_at->format('F d, Y') }}</strong><br>
                                                    <span class="text-primary">{{ $row->created_at->format('h:i:s A') }}</span><br>
                                                    <small class="text-info"><i class="far fa-clock"></i> {{ $row->created_at->diffForHumans() }}</small>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card bg-light mb-3 border">
                                    <div class="card-header bg-white">
                                        <h6 class="mb-0"><i class="fas fa-user text-success"></i> <strong>{{ __('user_information') }}</strong></h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th width="40%" class="text-muted"><i class="fas fa-user-circle"></i> {{ __('field_user') }}:</th>
                                                <td>
                                                    @if($row->user)
                                                        @if($row->user_type === 'App\Models\Student')
                                                            {{-- Student --}}
                                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-user-graduate"></i> Student</span><br>
                                                            <strong class="mt-1 d-inline-block">{{ $row->user->first_name }} {{ $row->user->last_name }}</strong><br>
                                                            <small class="text-muted">Student ID: {{ $row->user->student_id ?? $row->user->id }}</small>
                                                        @else
                                                            {{-- Admin/User --}}
                                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-user-shield"></i> Admin</span><br>
                                                            <strong class="mt-1 d-inline-block">{{ $row->user->name }}</strong><br>
                                                            <small class="text-muted">{{ $row->user->email ?? 'N/A' }}</small>
                                                        @endif
                                                    @else
                                                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-cog"></i> System</span><br>
                                                        <span class="text-muted">Automated Process</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted"><i class="fas fa-network-wired"></i> {{ __('field_ip_address') }}:</th>
                                                <td>
                                                    <code class="bg-white px-2 py-1">{{ $row->ip_address }}</code>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted"><i class="fas fa-globe"></i> {{ __('field_user_agent') }}:</th>
                                                <td>
                                                    @php
                                                        // Parse user agent to extract browser and OS
                                                        $userAgent = $row->user_agent;
                                                        $browser = 'Unknown';
                                                        $os = 'Unknown';
                                                        
                                                        // Detect browser
                                                        if(stripos($userAgent, 'Chrome') !== false && stripos($userAgent, 'Edg') === false) $browser = 'Chrome';
                                                        elseif(stripos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
                                                        elseif(stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false) $browser = 'Safari';
                                                        elseif(stripos($userAgent, 'Edg') !== false) $browser = 'Edge';
                                                        elseif(stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR') !== false) $browser = 'Opera';
                                                        
                                                        // Detect OS
                                                        if(stripos($userAgent, 'Windows') !== false) $os = 'Windows';
                                                        elseif(stripos($userAgent, 'Mac') !== false) $os = 'macOS';
                                                        elseif(stripos($userAgent, 'Linux') !== false) $os = 'Linux';
                                                        elseif(stripos($userAgent, 'Android') !== false) $os = 'Android';
                                                        elseif(stripos($userAgent, 'iOS') !== false || stripos($userAgent, 'iPhone') !== false) $os = 'iOS';
                                                    @endphp
                                                    <small>
                                                        <span class="badge badge-light">{{ $browser }}</span>
                                                        <span class="badge badge-light">{{ $os }}</span>
                                                    </small><br>
                                                    <small class="text-muted" style="word-break: break-all;">{{ Str::limit($userAgent, 80) }}</small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted"><i class="fas fa-link"></i> {{ __('field_url') }}:</th>
                                                <td>
                                                    <small style="word-break: break-all;">
                                                        <code class="bg-white">{{ $row->url }}</code>
                                                    </small>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        @if($row->description)
                        <div class="card mb-3 border">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="fas fa-file-alt text-info"></i> <strong>{{ __('field_description') }}</strong></h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0 lead">{{ $row->description }}</p>
                            </div>
                        </div>
                        @endif

                        <!-- Changes Made (for updated records) -->
                        @if($row->event == 'updated' && $row->changes && count($row->changes) > 0)
                        <div class="card mb-3 border border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-exchange-alt"></i> <strong>{{ __('changes_made') }}</strong></h6>
                                <small>Showing {{ count($row->changes) }} field(s) that were modified</small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="20%" class="border-right"><i class="fas fa-tag"></i> {{ __('field') }}</th>
                                                <th width="40%" class="border-right bg-danger text-white">
                                                    <i class="fas fa-arrow-left"></i> {{ __('old_value') }} (Before)
                                                </th>
                                                <th width="40%" class="bg-success text-white">
                                                    <i class="fas fa-arrow-right"></i> {{ __('new_value') }} (After)
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($row->changes as $field => $change)
                                            <tr>
                                                <td class="border-right">
                                                    <strong class="text-primary">{{ ucfirst(str_replace('_', ' ', $field)) }}</strong>
                                                </td>
                                                <td class="border-right bg-light">
                                                    @if(is_array($change['old']))
                                                        <pre class="mb-0 p-2 bg-white border rounded"><code>{{ json_encode($change['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                                    @elseif(is_null($change['old']) || $change['old'] === '')
                                                        <span class="text-muted font-italic">(empty)</span>
                                                    @elseif(is_bool($change['old']))
                                                        <span class="badge badge-{{ $change['old'] ? 'success' : 'secondary' }}">
                                                            {{ $change['old'] ? 'Yes' : 'No' }}
                                                        </span>
                                                    @elseif(is_numeric($change['old']) && strlen($change['old']) <= 15)
                                                        <strong class="text-danger">{{ number_format($change['old'], 2) }}</strong>
                                                    @else
                                                        <div style="max-height: 150px; overflow-y: auto;">
                                                            {{ $change['old'] }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="bg-light">
                                                    @if(is_array($change['new']))
                                                        <pre class="mb-0 p-2 bg-white border rounded"><code>{{ json_encode($change['new'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                                    @elseif(is_null($change['new']) || $change['new'] === '')
                                                        <span class="text-muted font-italic">(empty)</span>
                                                    @elseif(is_bool($change['new']))
                                                        <span class="badge badge-{{ $change['new'] ? 'success' : 'secondary' }}">
                                                            {{ $change['new'] ? 'Yes' : 'No' }}
                                                        </span>
                                                    @elseif(is_numeric($change['new']) && strlen($change['new']) <= 15)
                                                        <strong class="text-success">{{ number_format($change['new'], 2) }}</strong>
                                                    @else
                                                        <div style="max-height: 150px; overflow-y: auto;">
                                                            {{ $change['new'] }}
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Only fields that were modified are shown. Unchanged fields are hidden for clarity.
                                </small>
                            </div>
                        </div>
                        @endif

                        <!-- Old Values (for deleted items) -->
                        @if($row->event == 'deleted' && $row->old_values)
                        <div class="card mb-3 border border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0"><i class="fas fa-trash-alt"></i> <strong>{{ __('deleted_data') }}</strong></h6>
                                <small>Record data before deletion</small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="30%"><i class="fas fa-tag"></i> {{ __('field') }}</th>
                                                <th width="70%"><i class="fas fa-database"></i> {{ __('value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $oldValues = is_array($row->old_values) ? $row->old_values : json_decode($row->old_values, true);
                                                // Filter out null values and system fields
                                                $filteredValues = collect($oldValues)->filter(function($value, $key) {
                                                    return !is_null($value) && !in_array($key, ['password', 'remember_token', 'updated_at']);
                                                });
                                            @endphp
                                            @if(is_array($oldValues) && $filteredValues->count() > 0)
                                                @foreach($filteredValues as $field => $value)
                                                <tr>
                                                    <td><strong class="text-danger">{{ ucfirst(str_replace('_', ' ', $field)) }}</strong></td>
                                                    <td>
                                                        @if(is_array($value))
                                                            <pre class="mb-0 p-2 bg-light border rounded"><code>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                                        @elseif(is_bool($value))
                                                            <span class="badge badge-{{ $value ? 'success' : 'secondary' }}">
                                                                {{ $value ? 'Yes' : 'No' }}
                                                            </span>
                                                        @elseif(is_null($value) || $value === '')
                                                            <span class="text-muted font-italic">(empty)</span>
                                                        @elseif($field == 'created_at' || $field == 'deleted_at')
                                                            <i class="far fa-calendar-alt text-muted"></i> {{ \Carbon\Carbon::parse($value)->format('M d, Y h:i A') }}
                                                        @elseif(is_numeric($value) && !in_array($field, ['id', 'status']))
                                                            {{ number_format($value, 2) }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted">No data available</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-triangle text-danger"></i> 
                                    This data was permanently deleted from the system at {{ $row->created_at->format('M d, Y h:i A') }}.
                                </small>
                            </div>
                        </div>
                        @endif

                        <!-- New Values (for created items) -->
                        @if($row->event == 'created' && $row->new_values)
                        <div class="card mb-3 border border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-plus-circle"></i> <strong>{{ __('created_data') }}</strong></h6>
                                <small>Initial data when record was created</small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="30%"><i class="fas fa-tag"></i> {{ __('field') }}</th>
                                                <th width="70%"><i class="fas fa-database"></i> {{ __('value') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $newValues = is_array($row->new_values) ? $row->new_values : json_decode($row->new_values, true);
                                                // Filter out null values and system fields
                                                $filteredValues = collect($newValues)->filter(function($value, $key) {
                                                    return !is_null($value) && !in_array($key, ['password', 'remember_token', 'updated_at']);
                                                });
                                            @endphp
                                            @if(is_array($newValues) && $filteredValues->count() > 0)
                                                @foreach($filteredValues as $field => $value)
                                                <tr>
                                                    <td><strong class="text-success">{{ ucfirst(str_replace('_', ' ', $field)) }}</strong></td>
                                                    <td>
                                                        @if(is_array($value))
                                                            <pre class="mb-0 p-2 bg-light border rounded"><code>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                                        @elseif(is_bool($value))
                                                            <span class="badge badge-{{ $value ? 'success' : 'secondary' }}">
                                                                {{ $value ? 'Yes' : 'No' }}
                                                            </span>
                                                        @elseif(is_null($value) || $value === '')
                                                            <span class="text-muted font-italic">(empty)</span>
                                                        @elseif($field == 'created_at' || $field == 'deleted_at')
                                                            <i class="far fa-calendar-alt text-muted"></i> {{ \Carbon\Carbon::parse($value)->format('M d, Y h:i A') }}
                                                        @elseif(is_numeric($value) && !in_array($field, ['id', 'status']))
                                                            {{ number_format($value, 2) }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted">No data available</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle text-success"></i> 
                                    This record was created on {{ $row->created_at->format('M d, Y h:i A') }}.
                                </small>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_css')
<style>
    .card {
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .table th {
        font-weight: 600;
        color: #495057;
    }
    
    .badge {
        font-weight: 500;
        letter-spacing: 0.5px;
        padding: 6px 12px;
        font-size: 0.875rem;
    }
    
    code {
        color: #e83e8c;
        background-color: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.875rem;
    }
    
    pre {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        font-size: 0.85rem;
        max-height: 300px;
        overflow-y: auto;
    }
    
    pre code {
        background: none;
        padding: 0;
        color: #212529;
    }
    
    .alert-heading {
        font-size: 1.25rem;
    }
    
    .table-hover tbody tr:hover {
        background-color: #f1f3f5;
    }
    
    .border-right {
        border-right: 2px solid #dee2e6 !important;
    }
    
    .lead {
        font-size: 1.1rem;
        font-weight: 400;
        line-height: 1.6;
    }
    
    .card-header h6 {
        margin-bottom: 0;
        font-size: 1rem;
    }
    
    .table-borderless td,
    .table-borderless th {
        padding: 0.75rem;
    }
    
    .text-muted {
        color: #6c757d !important;
    }
    
    .font-italic {
        font-style: italic;
    }
    
    /* Print styles */
    @media print {
        .card-header-right,
        .breadcrumb,
        .btn {
            display: none !important;
        }
        
        .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
            page-break-inside: avoid;
        }
        
        .card-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
        }
    }
</style>
@endsection

@section('page_js')
<script>
    $(document).ready(function() {
        // Add print functionality
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Highlight changes on hover
        $('.table-hover tbody tr').hover(
            function() {
                $(this).find('td').css('font-weight', 'bold');
            },
            function() {
                $(this).find('td').css('font-weight', 'normal');
            }
        );
    });
</script>
@endsection
