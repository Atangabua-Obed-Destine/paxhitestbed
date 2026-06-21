<!-- Show modal content -->
<div id="showModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="myModalLabel">
                    <i class="{{ $row->icon ?? $row->file_icon }} fa-lg me-2"></i>
                    {{ __('modal_view') }} {{ $title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Details View Start -->
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="text-primary">{{ $row->title }}</h4>
                        <span class="badge bg-info mb-3">{{ $row->category_label }}</span>
                        
                        @if($row->description)
                        <p class="text-muted">{{ $row->description }}</p>
                        @endif
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="p-3 bg-light rounded">
                            <h3 class="text-primary mb-0">{{ number_format($row->download_count) }}</h3>
                            <small class="text-muted">Total Downloads</small>
                        </div>
                    </div>
                </div>
                
                <hr/>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-file me-2"></i>File Name:</strong><br> {{ $row->file_name }}</p>
                    </div>
                    <div class="col-md-3">
                        <p><strong><i class="fas fa-weight me-2"></i>File Size:</strong><br> {{ $row->formatted_file_size }}</p>
                    </div>
                    <div class="col-md-3">
                        <p><strong><i class="fas fa-file-alt me-2"></i>File Type:</strong><br> {{ $row->file_type ?? 'Unknown' }}</p>
                    </div>
                </div>
                
                <hr/>
                
                <div class="row">
                    <div class="col-md-4">
                        <p><strong><i class="fas fa-sort-numeric-down me-2"></i>Sort Order:</strong> {{ $row->sort_order }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong><i class="fas fa-calendar me-2"></i>Created:</strong> {{ $row->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong><i class="fas fa-toggle-on me-2"></i>Status:</strong>
                            @if( $row->status == 1 )
                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                            @else
                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                            @endif
                        </p>
                    </div>
                </div>
                
                @if($row->icon)
                <hr/>
                <p><strong><i class="fas fa-icons me-2"></i>Custom Icon:</strong> <code>{{ $row->icon }}</code> <i class="{{ $row->icon }}"></i></p>
                @endif
                <!-- Details View End -->
            </div>
            <div class="modal-footer">
                <a href="{{ route($route.'.download', $row->id) }}" class="btn btn-success"><i class="fas fa-download"></i> Download</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
            </div>
        </div>
    </div>
</div>
