<!-- Edit Modal -->
<div class="modal fade" id="editModal-{{ $row->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('btn_edit') }} {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Form Start -->
                    <div class="form-group">
                        <label for="title-{{ $row->id }}" class="form-label">{{ __('field_title') }} <span>*</span></label>
                        <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->title }}" required>
                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_title') }}</div>
                    </div>

                    <div class="form-group">
                        <label for="description-{{ $row->id }}" class="form-label">{{ __('field_description') }}</label>
                        <textarea class="form-control" name="description" id="description-{{ $row->id }}" rows="3">{{ $row->description }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="logo-{{ $row->id }}" class="form-label">Logo</label>
                        <input type="file" class="form-control" name="logo" id="logo-{{ $row->id }}">
                        <small class="form-text text-muted">Image/SVG (JPG, PNG, SVG) - Max 2MB | Leave empty to keep current</small>
                        @if(upload_exists($path.'/'.$row->logo))
                        <div class="mt-2">
                            <img src="{{ upload_asset($path.'/'.$row->logo) }}" alt="{{ $row->title }}" style="max-width: 150px;" class="img-thumbnail">
                        </div>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="url-{{ $row->id }}" class="form-label">Website URL</label>
                        <input type="url" class="form-control" name="url" id="url-{{ $row->id }}" value="{{ $row->url }}" placeholder="https://example.com">
                    </div>

                    <div class="form-group">
                        <label for="sort_order-{{ $row->id }}" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" id="sort_order-{{ $row->id }}" value="{{ $row->sort_order }}" min="0">
                        <small class="form-text text-muted">Lower numbers appear first</small>
                    </div>

                    <div class="form-group">
                        <label for="status-{{ $row->id }}" class="form-label">{{ __('field_status') }} <span>*</span></label>
                        <select class="form-control" name="status" id="status-{{ $row->id }}" required>
                            <option value="1" @if($row->status == 1) selected @endif>{{ __('status_active') }}</option>
                            <option value="0" @if($row->status == 0) selected @endif>{{ __('status_inactive') }}</option>
                        </select>
                    </div>
                    <!-- Form End -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
