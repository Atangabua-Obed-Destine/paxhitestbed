@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus text-success"></i> {{ __('modal_add') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>

                        <a href="{{ route($route.'.create') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                    </div>

                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card-block">
                      <div class="row">
                        <!-- Form Start -->
                        <div class="form-group col-md-8">
                            <label for="title">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" placeholder="e.g., Student Handbook 2024-2025" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group col-md-4">
                            <label for="category">Category <span>*</span></label>
                            <select class="form-control" name="category" id="category" required>
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $key => $label)
                                <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} Category
                            </div>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="description">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="3" placeholder="Brief description of this resource...">{{ old('description') }}</textarea>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} Description
                            </div>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="file">File <span>*</span> <small class="text-muted">(Max: 50MB - PDF, DOC, DOCX, XLS, XLSX, etc.)</small></label>
                            <input type="file" class="form-control" name="file" id="file" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} File
                            </div>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="icon">Custom Icon Class</label>
                            <input type="text" class="form-control" name="icon" id="icon" value="{{ old('icon') }}" placeholder="e.g., fas fa-book">
                            <small class="text-muted">Leave empty to auto-detect from file type</small>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="sort_order">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0">
                            <small class="text-muted">Lower numbers appear first</small>
                        </div>
                        <!-- Form End -->
                      </div>
                    </div>

                    <!-- Help Section -->
                    <div class="card-block border-top">
                        <div class="row">
                            <div class="col-md-12">
                                <h6><i class="fas fa-info-circle text-info"></i> Quick Tips</h6>
                                <ul class="text-muted mb-0">
                                    <li><strong>Student Guide:</strong> Upload comprehensive student handbooks, course guides, orientation materials.</li>
                                    <li><strong>School Calendarium:</strong> Upload academic calendars, important dates, semester schedules.</li>
                                    <li><strong>Academic Documents:</strong> Curriculum guides, syllabus templates, exam schedules.</li>
                                    <li><strong>Forms & Applications:</strong> Application forms, registration forms, request forms.</li>
                                    <li><strong>Policies & Regulations:</strong> Code of conduct, examination rules, disciplinary policies.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                    </div>
                    </form>
                </div>
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
