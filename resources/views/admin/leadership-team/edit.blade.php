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
                        <label for="name-{{ $row->id }}" class="form-label">{{ __('field_name') }} <span>*</span></label>
                        <input type="text" class="form-control" name="name" id="name-{{ $row->id }}" value="{{ $row->name }}" required>
                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_name') }}</div>
                    </div>

                    <div class="form-group">
                        <label for="designation-{{ $row->id }}" class="form-label">Designation <span>*</span></label>
                        <input type="text" class="form-control" name="designation" id="designation-{{ $row->id }}" value="{{ $row->designation }}" placeholder="e.g., Vice Chancellor" required>
                        <div class="invalid-feedback">{{ __('required_field') }} Designation</div>
                    </div>

                    <div class="form-group">
                        <label for="bio-{{ $row->id }}" class="form-label">Biography</label>
                        <textarea class="form-control" name="bio" id="bio-{{ $row->id }}" rows="4">{{ $row->bio }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="email-{{ $row->id }}" class="form-label">{{ __('field_email') }}</label>
                        <input type="email" class="form-control" name="email" id="email-{{ $row->id }}" value="{{ $row->email }}">
                    </div>

                    <div class="form-group">
                        <label for="phone-{{ $row->id }}" class="form-label">{{ __('field_phone') }}</label>
                        <input type="text" class="form-control" name="phone" id="phone-{{ $row->id }}" value="{{ $row->phone }}">
                    </div>

                    <div class="form-group">
                        <label for="photo-{{ $row->id }}" class="form-label">Photo</label>
                        <input type="file" class="form-control" name="photo" id="photo-{{ $row->id }}">
                        <small class="form-text text-muted">Image (JPG, PNG) - Max 2MB | Leave empty to keep current</small>
                        @if(upload_exists($path.'/'.$row->photo))
                        <div class="mt-2">
                            <img src="{{ upload_asset($path.'/'.$row->photo) }}" alt="{{ $row->name }}" style="max-width: 150px;" class="img-thumbnail">
                        </div>
                        @endif
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
