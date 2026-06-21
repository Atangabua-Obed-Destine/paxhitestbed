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
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="faculty-{{ $row->id }}">{{ __('field_faculty') }} <span>*</span></label>
                                        <select class="form-control" name="faculty" id="faculty-{{ $row->id }}" required>
                                            <option value="">{{ __('select') }}</option>
                                            @foreach( $faculties as $faculty )
                                            <option value="{{ $faculty->id }}" @if($row->faculty_id == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                            @endforeach
                                        </select>

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('field_faculty') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="academic_department-{{ $row->id }}">Academic Department</label>
                                        <select class="form-control" name="academic_department" id="academic_department-{{ $row->id }}">
                                            <option value="">{{ __('select') }}</option>
                                            @foreach( $academicDepartments as $dept )
                                            <option value="{{ $dept->id }}" data-faculty-id="{{ $dept->faculty_id }}" @if($row->academic_department_id == $dept->id) selected @endif>{{ $dept->title }} ({{ $dept->faculty->title }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="degree_type-{{ $row->id }}">Degree Type</label>
                                        <select class="form-control" name="degree_type" id="degree_type-{{ $row->id }}">
                                            <option value="">{{ __('select') }}</option>
                                            @foreach( $degreeTypes as $degreeType )
                                            <option value="{{ $degreeType->id }}" @if($row->degree_type_id == $degreeType->id) selected @endif>{{ $degreeType->title }} ({{ $degreeType->shortcode }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="title-{{ $row->id }}" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->title }}" required>

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('field_title') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="shortcode-{{ $row->id }}" class="form-label">{{ __('field_shortcode') }} <span>*</span></label>
                                        <input type="text" class="form-control" name="shortcode" id="shortcode-{{ $row->id }}" value="{{ $row->shortcode }}" required>

                                        <div class="invalid-feedback">
                                          {{ __('required_field') }} {{ __('field_shortcode') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="duration-{{ $row->id }}">Duration</label>
                                        <input type="text" class="form-control" name="duration" id="duration-{{ $row->id }}" value="{{ $row->duration }}" placeholder="e.g., 3 Years">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="credit-{{ $row->id }}">Credits</label>
                                        <input type="text" class="form-control" name="credit" id="credit-{{ $row->id }}" value="{{ $row->credit }}" placeholder="e.g., 120 Credits">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="validity_years-{{ $row->id }}">ID Card Validity (Years)</label>
                                        <input type="number" class="form-control" name="validity_years" id="validity_years-{{ $row->id }}" value="{{ $row->validity_years ?? 4 }}" min="1" max="10" placeholder="e.g., 4">
                                        <small class="form-text text-muted">Years the ID card is valid</small>
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

                            <div class="form-group">
                                <label for="requirements-{{ $row->id }}">Entry Requirements</label>
                                <textarea class="form-control texteditor" name="requirements" id="requirements-{{ $row->id }}">{{ $row->requirements }}</textarea>
                            </div>

                            <div class="form-group">
                                <label for="career_prospects-{{ $row->id }}">Career Prospects</label>
                                <textarea class="form-control texteditor" name="career_prospects" id="career_prospects-{{ $row->id }}">{{ $row->career_prospects }}</textarea>
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
                                            <img src="{{ asset('uploads/programs/'.$row->featured_image) }}" alt="Featured Image" class="img-thumbnail" style="max-width: 200px;">
                                            <small class="d-block text-muted">Current Image</small>
                                        </div>
                                        @endif
                                        <input type="file" class="form-control" name="featured_image" id="featured_image-{{ $row->id }}" accept="image/*">
                                        <small class="form-text text-muted">Leave empty to keep current image</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="banner_image-{{ $row->id }}">Banner Image</label>
                                        @if($row->banner_image)
                                        <div class="mb-2">
                                            <img src="{{ asset('uploads/programs/'.$row->banner_image) }}" alt="Banner Image" class="img-thumbnail" style="max-width: 200px;">
                                            <small class="d-block text-muted">Current Image</small>
                                        </div>
                                        @endif
                                        <input type="file" class="form-control" name="banner_image" id="banner_image-{{ $row->id }}" accept="image/*">
                                        <small class="form-text text-muted">Leave empty to keep current image</small>
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