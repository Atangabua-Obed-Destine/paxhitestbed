@extends('admin.layouts.master')

@section('title', $title)

@section('content')

<!-- Start Content -->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('All Dynamic Popups') }}</h5>
                        <div class="card-header-right">
                            @can($access.'-create')
                            <a href="{{ route($route.'.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('Create New Popup') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <div class="card-block">
                        <!-- Search & Filter -->
                        <form method="GET" action="{{ route($route.'.index') }}" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="{{ __('Search by title or summary...') }}"
                                           value="{{ request('search') }}">
                                </div>
                                <div class="col-md-3">
                                    <select name="target_area" class="form-control">
                                        <option value="">{{ __('All Target Areas') }}</option>
                                        @foreach($targetAreas as $key => $label)
                                        <option value="{{ $key }}" {{ request('target_area') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-control">
                                        <option value="">{{ __('All Status') }}</option>
                                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-search"></i> {{ __('Search') }}
                                    </button>
                                    <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-sync"></i> {{ __('Reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Bulk Actions -->
                        @can($access.'-delete')
                        <form id="bulkDeleteForm" action="{{ route($route.'.bulk-delete') }}" method="POST" class="mb-3">
                            @csrf
                            <div class="d-flex align-items-center">
                                <select id="bulkAction" class="form-control" style="width: 200px;">
                                    <option value="">{{ __('Bulk Action') }}</option>
                                    <option value="delete">{{ __('Delete Selected') }}</option>
                                </select>
                                <button type="button" id="applyBulkAction" class="btn btn-sm btn-danger ml-2" disabled>
                                    {{ __('Apply') }}
                                </button>
                                <span id="selectedCount" class="ml-3 text-muted"></span>
                            </div>
                        </form>
                        @endcan

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        @can($access.'-delete')
                                        <th width="30">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        @endcan
                                        <th width="80">{{ __('Image') }}</th>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Target Areas') }}</th>
                                        <th width="80">{{ __('Priority') }}</th>
                                        <th width="120">{{ __('Schedule') }}</th>
                                        <th width="100">{{ __('Status') }}</th>
                                        <th width="120">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $row)
                                    <tr>
                                        @can($access.'-delete')
                                        <td>
                                            @if(!$row->is_pinned)
                                            <input type="checkbox" name="ids[]" value="{{ $row->id }}" 
                                                   class="form-check-input row-checkbox">
                                            @else
                                            <i class="fas fa-thumbtack text-warning" title="{{ __('Pinned') }}"></i>
                                            @endif
                                        </td>
                                        @endcan
                                        <td>
                                            @if($row->image)
                                            <img src="{{ $row->image_url }}" alt="{{ $row->title }}" 
                                                 class="img-thumbnail" style="max-width: 80px; max-height: 50px;">
                                            @else
                                            <span class="text-muted"><i class="fas fa-image"></i></span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>{{ Str::limit($row->title, 40) }}</strong>
                                            @if($row->summary)
                                            <br><small class="text-muted">{{ Str::limit($row->summary, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            {!! $row->target_areas_badges !!}
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $row->priority }}</span>
                                        </td>
                                        <td>
                                            @if($row->start_date || $row->end_date)
                                            <small>
                                                @if($row->start_date)
                                                <i class="fas fa-play text-success"></i> {{ $row->start_date->format('M d, Y') }}<br>
                                                @endif
                                                @if($row->end_date)
                                                <i class="fas fa-stop text-danger"></i> {{ $row->end_date->format('M d, Y') }}
                                                @endif
                                            </small>
                                            @else
                                            <small class="text-muted">{{ __('Always') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input status-toggle" 
                                                       id="status{{ $row->id }}" 
                                                       data-id="{{ $row->id }}"
                                                       {{ $row->status ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="status{{ $row->id }}"></label>
                                            </div>
                                            <small>{!! $row->status_badge !!}</small>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="previewPopup({{ $row->id }})">
                                                        <i class="fas fa-eye text-info"></i> {{ __('Preview') }}
                                                    </a>
                                                    @can($access.'-edit')
                                                    <a class="dropdown-item" href="{{ route($route.'.edit', $row->id) }}">
                                                        <i class="fas fa-edit text-primary"></i> {{ __('Edit') }}
                                                    </a>
                                                    <a class="dropdown-item pin-toggle" href="#" 
                                                       data-id="{{ $row->id }}" data-pinned="{{ $row->is_pinned ? '1' : '0' }}">
                                                        <i class="fas fa-thumbtack {{ $row->is_pinned ? 'text-warning' : 'text-muted' }}"></i>
                                                        {{ $row->is_pinned ? __('Unpin') : __('Pin') }}
                                                    </a>
                                                    @endcan
                                                    @can($access.'-delete')
                                                    @if(!$row->is_pinned)
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route($route.'.destroy', $row->id) }}" method="POST" 
                                                          class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash"></i> {{ __('Delete') }}
                                                        </button>
                                                    </form>
                                                    @endif
                                                    @endcan
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">{{ __('No dynamic popups found.') }}</p>
                                            @can($access.'-create')
                                            <a href="{{ route($route.'.create') }}" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> {{ __('Create Your First Popup') }}
                                            </a>
                                            @endcan
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $rows->withQueryString()->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content -->

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-0" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Select all checkbox
    $('#selectAll').change(function() {
        $('.row-checkbox').prop('checked', $(this).prop('checked'));
        updateSelectedCount();
    });

    // Individual checkbox change
    $('.row-checkbox').change(function() {
        updateSelectedCount();
        $('#selectAll').prop('checked', $('.row-checkbox:checked').length === $('.row-checkbox').length);
    });

    function updateSelectedCount() {
        var count = $('.row-checkbox:checked').length;
        $('#selectedCount').text(count > 0 ? count + ' {{ __("selected") }}' : '');
        $('#applyBulkAction').prop('disabled', count === 0);
    }

    // Apply bulk action
    $('#applyBulkAction').click(function() {
        var action = $('#bulkAction').val();
        if (action === 'delete') {
            if (confirm('{{ __("Are you sure you want to delete selected popups?") }}')) {
                var ids = [];
                $('.row-checkbox:checked').each(function() {
                    ids.push($(this).val());
                });
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'ids',
                    value: JSON.stringify(ids)
                }).appendTo('#bulkDeleteForm');
                
                $('#bulkDeleteForm').submit();
            }
        }
    });

    // Status toggle
    $('.status-toggle').change(function() {
        var id = $(this).data('id');
        var checkbox = $(this);
        
        $.ajax({
            url: '{{ url("admin/marketing/dynamic-popup") }}/' + id + '/toggle-status',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                checkbox.prop('checked', !checkbox.prop('checked'));
                toastr.error('{{ __("Failed to update status") }}');
            }
        });
    });

    // Pin toggle
    $('.pin-toggle').click(function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        
        $.ajax({
            url: '{{ url("admin/marketing/dynamic-popup") }}/' + id + '/toggle-pinned',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('{{ __("Failed to update pin status") }}');
            }
        });
    });

    // Delete confirmation
    $('.delete-form').submit(function(e) {
        if (!confirm('{{ __("Are you sure you want to delete this popup?") }}')) {
            e.preventDefault();
        }
    });
});

// Preview popup function
function previewPopup(id) {
    $.get('{{ url("admin/marketing/dynamic-popup") }}/' + id, function(html) {
        // Extract popup data and show preview
        $('#previewModal').modal('show');
    });
    
    // For now, show a simple preview via AJAX
    $.get('{{ url("api/popups/all") }}', function(response) {
        var popup = response.popups.find(p => p.id == id);
        if (popup) {
            var html = `
                <div class="dynamic-popup-preview" style="position: relative;">
                    <button type="button" class="close" data-dismiss="modal" 
                            style="position: absolute; right: 10px; top: 10px; z-index: 10; background: white; border-radius: 50%; width: 30px; height: 30px;">
                        <span>&times;</span>
                    </button>
                    ${popup.image ? `<img src="${popup.image}" class="w-100" style="max-height: 200px; object-fit: cover;">` : ''}
                    <div class="p-4">
                        <h4 class="mb-2">${popup.title}</h4>
                        ${popup.summary ? `<p class="text-muted mb-3">${popup.summary}</p>` : ''}
                        ${popup.button_text ? `
                            <a href="${popup.link || '#'}" class="btn" target="_blank"
                               style="background-color: ${popup.button_color}; color: ${popup.button_text_color === 'light' ? '#fff' : '#000'};">
                                ${popup.button_text} <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        ` : ''}
                    </div>
                </div>
            `;
            $('#previewContent').html(html);
            $('#previewModal').modal('show');
        }
    });
}
</script>
@endpush
