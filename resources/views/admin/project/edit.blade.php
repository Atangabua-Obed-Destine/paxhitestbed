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
                        <label for="faculty_id-{{ $row->id }}" class="form-label">{{ __('field_faculty') }}</label>
                        <select class="form-control" name="faculty_id" id="faculty_id-{{ $row->id }}">
                            <option value="">{{ __('select') }}</option>
                            @foreach($faculties as $faculty)
                            <option value="{{ $faculty->id }}" @if($row->faculty_id == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="theme-{{ $row->id }}" class="form-label">{{ __('field_theme') }}</label>
                        <input type="text" class="form-control" name="theme" id="theme-{{ $row->id }}" value="{{ $row->theme }}" placeholder="e.g., Sustainability, Health">
                    </div>

                    <div class="form-group">
                        <label for="lead_researcher-{{ $row->id }}" class="form-label">Lead Researcher</label>
                        <input type="text" class="form-control" name="lead_researcher" id="lead_researcher-{{ $row->id }}" value="{{ $row->lead_researcher }}">
                    </div>

                    <div class="form-group">
                        <label for="description-{{ $row->id }}" class="form-label">{{ __('field_description') }} <span>*</span></label>
                        <textarea class="form-control" name="description" id="description-{{ $row->id }}" rows="4" required>{{ $row->description }}</textarea>
                        <div class="invalid-feedback">{{ __('required_field') }} {{ __('field_description') }}</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date-{{ $row->id }}" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date-{{ $row->id }}" value="{{ $row->start_date }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date-{{ $row->id }}" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date-{{ $row->id }}" value="{{ $row->end_date }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status-{{ $row->id }}" class="form-label">{{ __('field_status') }} <span>*</span></label>
                        <select class="form-control" name="status" id="status-{{ $row->id }}" required>
                            <option value="ongoing" @if($row->status == 'ongoing') selected @endif>Ongoing</option>
                            <option value="completed" @if($row->status == 'completed') selected @endif>Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="attach-{{ $row->id }}" class="form-label">Project Image</label>
                        <input type="file" class="form-control" name="attach" id="attach-{{ $row->id }}">
                        <small class="form-text text-muted">Image (JPG, PNG) - Max 2MB | Leave empty to keep current</small>
                        @if(is_file('uploads/'.$path.'/'.$row->attach))
                        <div class="mt-2">
                            <img src="{{ asset('uploads/'.$path.'/'.$row->attach) }}" alt="{{ $row->title }}" style="max-width: 200px;" class="img-thumbnail">
                        </div>
                        @endif
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="featured" id="featured-{{ $row->id }}" value="1" @if($row->featured) checked @endif>
                            <label class="form-check-label" for="featured-{{ $row->id }}">Featured Project</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="is_active-{{ $row->id }}" class="form-label">{{ __('field_active_status') }} <span>*</span></label>
                        <select class="form-control" name="is_active" id="is_active-{{ $row->id }}" required>
                            <option value="1" @if($row->is_active == 1) selected @endif>{{ __('status_active') }}</option>
                            <option value="0" @if($row->is_active == 0) selected @endif>{{ __('status_inactive') }}</option>
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
