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
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_audit_trail', 2) }}</a></li>
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
                        <h5>{{ trans_choice('module_audit_trail', 2) }}</h5>
                        <div class="card-header-right">
                            @php
                                $activeFilters = collect(request()->only(['user_id', 'event', 'auditable_type', 'search', 'date_from', 'date_to']))->filter()->count();
                            @endphp
                            <button type="button" class="btn btn-sm btn-outline-primary" id="filterToggleBtn">
                                <i class="fas fa-filter"></i> {{ __('filter') }}
                                @if($activeFilters > 0)
                                    <span class="badge badge-danger">{{ $activeFilters }}</span>
                                @endif
                            </button>
                            @can('audit-log-export')
                            <a href="{{ route('admin.audit-log.export', request()->query()) }}" class="btn btn-sm btn-success">
                                <i class="fas fa-download"></i> {{ __('export') }}
                            </a>
                            @endcan
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <div id="filterCollapse" style="display: {{ $activeFilters > 0 ? 'block' : 'none' }};">
                        <div class="card-body">
                            <form method="GET" action="{{ route('admin.audit-log.index') }}">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="user_id">{{ __('field_user') }}</label>
                                            <select name="user_id" id="user_id" class="form-control">
                                                <option value="">{{ __('all') }}</option>
                                                @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                                    {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="event">{{ __('field_event') }}</label>
                                            <select name="event" id="event" class="form-control">
                                                <option value="">{{ __('all') }}</option>
                                                @foreach($events as $event)
                                                <option value="{{ $event }}" {{ request('event') == $event ? 'selected' : '' }}>
                                                    {{ ucfirst(str_replace('_', ' ', $event)) }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="auditable_type">{{ __('field_model') }}</label>
                                            <select name="auditable_type" id="auditable_type" class="form-control">
                                                <option value="">{{ __('all') }}</option>
                                                @foreach($models as $model)
                                                <option value="{{ $model['short'] }}" {{ request('auditable_type') == $model['short'] ? 'selected' : '' }}>
                                                    {{ $model['short'] }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="search">{{ __('search') }}</label>
                                            <input type="text" name="search" id="search" class="form-control" value="{{ request('search') }}" placeholder="{{ __('search') }}...">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="date_from">{{ __('field_from_date') }}</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="date_to">{{ __('field_to_date') }}</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>&nbsp;</label><br>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> {{ __('filter') }}
                                            </button>
                                            <a href="{{ route('admin.audit-log.index') }}" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> {{ __('reset') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Active Filters Summary -->
                        @if($activeFilters > 0)
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <strong><i class="fas fa-filter"></i> Active Filters:</strong>
                            <div class="mt-2">
                                @if(request('user_id'))
                                    @php $selectedUser = $users->firstWhere('id', request('user_id')); @endphp
                                    <span class="badge badge-primary mr-1">User: {{ $selectedUser ? $selectedUser->first_name . ' ' . $selectedUser->last_name : 'Unknown' }}</span>
                                @endif
                                @if(request('event'))
                                    <span class="badge badge-primary mr-1">Event: {{ ucfirst(str_replace('_', ' ', request('event'))) }}</span>
                                @endif
                                @if(request('auditable_type'))
                                    <span class="badge badge-primary mr-1">Model: {{ request('auditable_type') }}</span>
                                @endif
                                @if(request('search'))
                                    <span class="badge badge-primary mr-1">Search: "{{ request('search') }}"</span>
                                @endif
                                @if(request('date_from'))
                                    <span class="badge badge-primary mr-1">From: {{ request('date_from') }}</span>
                                @endif
                                @if(request('date_to'))
                                    <span class="badge badge-primary mr-1">To: {{ request('date_to') }}</span>
                                @endif
                            </div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 5%;">{{ __('field_id') }}</th>
                                        <th style="width: 12%;">{{ __('field_date_time') }}</th>
                                        <th style="width: 15%;">{{ __('field_user') }}</th>
                                        <th style="width: 10%;">{{ __('field_event') }}</th>
                                        <th style="width: 13%;">{{ __('field_model') }}</th>
                                        <th style="width: 30%;">{{ __('field_description') }}</th>
                                        <th style="width: 10%;">{{ __('field_ip_address') }}</th>
                                        <th style="width: 5%;">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $row)
                                    <tr>
                                        <td><strong>#{{ $row->id }}</strong></td>
                                        <td>
                                            <i class="far fa-calendar text-primary"></i> {{ $row->created_at->format('M d, Y') }}<br>
                                            <i class="far fa-clock text-muted"></i> <small class="text-muted">{{ $row->created_at->format('h:i A') }}</small><br>
                                            <small class="text-info">{{ $row->created_at->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            @if($row->user)
                                                @if($row->user_type === 'App\Models\Student')
                                                    {{-- Student --}}
                                                    <i class="fas fa-user-graduate text-info"></i> <strong>{{ $row->user->first_name }} {{ $row->user->last_name }}</strong><br>
                                                    <small class="badge badge-info">Student #{{ $row->user->student_id ?? $row->user->id }}</small>
                                                @else
                                                    {{-- Admin/User --}}
                                                    <i class="fas fa-user-shield text-success"></i> <strong>{{ $row->user->name }}</strong><br>
                                                    <small class="text-muted">{{ Str::limit($row->user->email ?? 'N/A', 20) }}</small>
                                                @endif
                                            @else
                                                <i class="fas fa-cog text-secondary"></i> <span class="text-muted">{{ __('system') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badgeClass = 'badge-secondary';
                                                $icon = 'fa-circle';
                                                
                                                // Determine badge color and icon based on event
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
                                                elseif(in_array($row->event, ['mark_submitted', 'mark_updated'])) {
                                                    $badgeClass = 'badge-warning';
                                                    $icon = 'fa-check-square';
                                                }
                                                elseif($row->event == 'fee_payment') {
                                                    $badgeClass = 'badge-success';
                                                    $icon = 'fa-money-bill-wave';
                                                }
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">
                                                <i class="fas {{ $icon }}"></i> {{ $row->event_name }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                // Categorize models for better display
                                                $modelCategory = 'default';
                                                $categoryIcon = 'fa-database';
                                                $categoryColor = 'secondary';
                                                
                                                $financialModels = ['Transaction', 'Payroll', 'JournalEntry', 'Fee', 'Income', 'Expense', 'ChartOfAccount', 'FiscalYear'];
                                                $academicModels = ['Faculty', 'Program', 'Session', 'Semester', 'Subject', 'Batch', 'ClassRoutine'];
                                                $studentModels = ['Student', 'StudentEnroll', 'EnrollSubject'];
                                                
                                                if(in_array($row->model_name, $financialModels)) {
                                                    $categoryIcon = 'fa-dollar-sign';
                                                    $categoryColor = 'success';
                                                }
                                                elseif(in_array($row->model_name, $academicModels)) {
                                                    $categoryIcon = 'fa-graduation-cap';
                                                    $categoryColor = 'primary';
                                                }
                                                elseif(in_array($row->model_name, $studentModels)) {
                                                    $categoryIcon = 'fa-user-graduate';
                                                    $categoryColor = 'info';
                                                }
                                            @endphp
                                            <span class="badge badge-{{ $categoryColor }}">
                                                <i class="fas {{ $categoryIcon }}"></i> {{ $row->model_name }}
                                            </span>
                                            @if($row->auditable_id)
                                            <br><small class="text-muted">ID: #{{ $row->auditable_id }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="audit-description">
                                                {{ Str::limit($row->description, 80) }}
                                                @if(strlen($row->description) > 80)
                                                    <a href="{{ route('admin.audit-log.show', $row->id) }}" class="text-primary">
                                                        <small>...read more</small>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fas fa-network-wired text-muted"></i> <small>{{ $row->ip_address }}</small>
                                        </td>
                                        <td class="text-center">
                                            @can('audit-log-view')
                                            <a href="{{ route('admin.audit-log.show', $row->id) }}" 
                                               class="btn btn-sm btn-outline-info" 
                                               title="{{ __('view') }}"
                                               data-toggle="tooltip">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">{{ __('no_data_found') }}</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $rows->links() }}
                        </div>

                        <!-- Statistics -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong>{{ __('statistics') }}:</strong> 
                                    {{ __('showing') }} {{ $rows->firstItem() ?? 0 }} {{ __('to') }} {{ $rows->lastItem() ?? 0 }} 
                                    {{ __('of') }} {{ $rows->total() }} {{ __('entries') }}
                                </div>
                            </div>
                        </div>
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
    .audit-description {
        line-height: 1.6;
        color: #333;
    }
    
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
        transform: scale(1.01);
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .badge {
        font-weight: 500;
        letter-spacing: 0.5px;
    }
    
    .card {
        transition: all 0.3s ease;
    }
    
    .card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .table thead th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
    }
</style>
@endsection

@section('page_js')
<script>
    $(document).ready(function() {
        // Simple toggle for filter section
        $('#filterToggleBtn').on('click', function() {
            $('#filterCollapse').slideToggle(300);
        });
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Add smooth scrolling
        $('a[href^="#"]').on('click', function(event) {
            var target = $(this.getAttribute('href'));
            if( target.length ) {
                event.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 1000);
            }
        });
    });
</script>
@endsection
