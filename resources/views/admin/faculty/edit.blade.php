    <!-- Edit modal content -->
    <div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
              <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ __('modal_edit') }} {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#edit-basic-{{ $row->id }}" role="tab">
                                <i class="fas fa-info-circle"></i> Basic
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#edit-content-{{ $row->id }}" role="tab">
                                <i class="fas fa-file-alt"></i> Content
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#edit-dean-{{ $row->id }}" role="tab">
                                <i class="fas fa-user-tie"></i> Dean
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#edit-images-{{ $row->id }}" role="tab">
                                <i class="fas fa-images"></i> Images
                            </a>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content mt-3">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane active" id="edit-basic-{{ $row->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="sector_id-{{ $row->id }}" class="form-label">{{ __('Sector') }}</label>
                                        <select class="form-control" name="sector_id" id="sector_id-{{ $row->id }}">
                                            <option value="">{{ __('Select Sector') }}</option>
                                            @foreach($sectors as $sector)
                                            <option value="{{ $sector->id }}" @if($row->sector_id == $sector->id) selected @endif>{{ $sector->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title-{{ $row->id }}" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->title }}" required>

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('field_title') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="shortcode-{{ $row->id }}" class="form-label">{{ __('field_shortcode') }}</label>
                                        <input type="text" class="form-control" name="shortcode" id="shortcode-{{ $row->id }}" value="{{ $row->shortcode }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="matric_code-{{ $row->id }}" class="form-label">{{ __('Faculty Matricule Code') }}</label>
                                        <input type="text" class="form-control" name="matric_code" id="matric_code-{{ $row->id }}" value="{{ $row->matric_code }}" placeholder="e.g. 01">
                                        <small class="form-text text-muted">Used for generating student matricule numbers.</small>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="status-{{ $row->id }}" class="form-label">{{ __('select_status') }}</label>
                                        <select class="form-control" name="status" id="status-{{ $row->id }}">
                                            <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                                            <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content Tab -->
                        <div class="tab-pane" id="edit-content-{{ $row->id }}" role="tabpanel">
                            <div class="form-group">
                                <label for="excerpt-{{ $row->id }}">Excerpt / Short Description</label>
                                <textarea class="form-control" name="excerpt" id="excerpt-{{ $row->id }}" rows="3">{{ $row->excerpt }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="description-{{ $row->id }}">Full Description</label>
                                <textarea class="form-control texteditor" name="description" id="description-{{ $row->id }}">{{ $row->description }}</textarea>
                            </div>
                        </div>

                        <!-- Dean Info Tab -->
                        <div class="tab-pane" id="edit-dean-{{ $row->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dean_name-{{ $row->id }}">Dean Name</label>
                                        <input type="text" class="form-control" name="dean_name" id="dean_name-{{ $row->id }}" value="{{ $row->dean_name }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email-{{ $row->id }}">Email</label>
                                        <input type="email" class="form-control" name="email" id="email-{{ $row->id }}" value="{{ $row->email }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone-{{ $row->id }}">Phone</label>
                                        <input type="text" class="form-control" name="phone" id="phone-{{ $row->id }}" value="{{ $row->phone }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="website-{{ $row->id }}">Website URL</label>
                                        <input type="url" class="form-control" name="website" id="website-{{ $row->id }}" value="{{ $row->website }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Images Tab -->
                        <div class="tab-pane" id="edit-images-{{ $row->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="featured_image-{{ $row->id }}">Featured Image</label>
                                        @if($row->featured_image)
                                        <div class="mb-2">
                                            <img src="{{ asset('uploads/faculties/'.$row->featured_image) }}" alt="Featured Image" class="img-thumbnail" style="max-width: 200px;">
                                            <small class="d-block text-muted">Current Image</small>
                                        </div>
                                        @endif
                                        <input type="file" class="form-control" name="featured_image" id="featured_image-{{ $row->id }}" accept="image/*">
                                        <small class="form-text text-muted">Leave empty to keep current</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="banner_image-{{ $row->id }}">Banner Image</label>
                                        @if($row->banner_image)
                                        <div class="mb-2">
                                            <img src="{{ asset('uploads/faculties/'.$row->banner_image) }}" alt="Banner Image" class="img-thumbnail" style="max-width: 200px;">
                                            <small class="d-block text-muted">Current Image</small>
                                        </div>
                                        @endif
                                        <input type="file" class="form-control" name="banner_image" id="banner_image-{{ $row->id }}" accept="image/*">
                                        <small class="form-text text-muted">Leave empty to keep current</small>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="dean_photo-{{ $row->id }}">Dean Photo</label>
                                        @if($row->dean_photo)
                                        <div class="mb-2">
                                            <img src="{{ asset('uploads/faculties/deans/'.$row->dean_photo) }}" alt="Dean Photo" class="img-thumbnail" style="max-width: 200px;">
                                            <small class="d-block text-muted">Current Photo</small>
                                        </div>
                                        @endif
                                        <input type="file" class="form-control" name="dean_photo" id="dean_photo-{{ $row->id }}" accept="image/*">
                                        <small class="form-text text-muted">Leave empty to keep current</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Form End -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                </div>

              </form>
            </div>
        </div>
    </div>