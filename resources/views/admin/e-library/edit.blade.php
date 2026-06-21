@extends('admin.layouts.master')

@section('title', 'Edit Book')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-primary text-white me-2">
                <i class="mdi mdi-book-edit"></i>
            </span> Edit Book
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Book</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Edit: {{ $book->title }}</h4>
                    <p class="card-description">Update book information and files</p>

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Validation Errors:</strong>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('admin.e-library.update', $book->id) }}" method="POST" enctype="multipart/form-data" id="bookEditForm">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <!-- Basic Information -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Basic Information</h5>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="title" class="form-label">Book Title <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $book->title) }}" required>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="subtitle" class="form-label">Subtitle (Optional)</label>
                                                <input type="text" class="form-control" id="subtitle" name="subtitle" value="{{ old('subtitle', $book->subtitle) }}">
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="authors" class="form-label">Author(s) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="authors" name="authors" 
                                                       value="{{ old('authors', is_array($book->authors) ? implode(', ', $book->authors) : $book->authors) }}" 
                                                       placeholder="Separate multiple authors with commas" required>
                                                <small class="text-muted">Example: John Doe, Jane Smith</small>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                    <option value="">Select a category</option>
                                                    @foreach($categories as $category)
                                                        <option value="{{ $category->id }}" {{ old('category_id', $book->category_id) == $category->id ? 'selected' : '' }}>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $book->description) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Publication Details -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Publication Details</h5>

                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="isbn" class="form-label">ISBN</label>
                                                <input type="text" class="form-control" id="isbn" name="isbn" value="{{ old('isbn', $book->isbn) }}">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="isbn_13" class="form-label">ISBN-13</label>
                                                <input type="text" class="form-control" id="isbn_13" name="isbn_13" value="{{ old('isbn_13', $book->isbn_13) }}">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="language" class="form-label">Language</label>
                                                <select class="form-select" id="language" name="language">
                                                    <option value="English" {{ old('language', $book->language) == 'English' ? 'selected' : '' }}>English</option>
                                                    <option value="Spanish" {{ old('language', $book->language) == 'Spanish' ? 'selected' : '' }}>Spanish</option>
                                                    <option value="French" {{ old('language', $book->language) == 'French' ? 'selected' : '' }}>French</option>
                                                    <option value="German" {{ old('language', $book->language) == 'German' ? 'selected' : '' }}>German</option>
                                                    <option value="Chinese" {{ old('language', $book->language) == 'Chinese' ? 'selected' : '' }}>Chinese</option>
                                                    <option value="Other" {{ old('language', $book->language) == 'Other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="publisher" class="form-label">Publisher</label>
                                                <input type="text" class="form-control" id="publisher" name="publisher" value="{{ old('publisher', $book->publisher) }}">
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label for="publish_date" class="form-label">Publish Date</label>
                                                <input type="date" class="form-control" id="publish_date" name="publish_date" value="{{ old('publish_date', $book->publish_date) }}">
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label for="number_of_pages" class="form-label">Pages</label>
                                                <input type="number" class="form-control" id="number_of_pages" name="number_of_pages" value="{{ old('number_of_pages', $book->number_of_pages) }}">
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="subjects" class="form-label">Subjects/Tags</label>
                                                <input type="text" class="form-control" id="subjects" name="subjects" 
                                                       value="{{ old('subjects', is_array($book->subjects) ? implode(', ', $book->subjects) : '') }}" 
                                                       placeholder="Separate multiple subjects with commas">
                                                <small class="text-muted">Example: Programming, Web Development, Technology</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Source Information -->
                                @if($book->source == 'openlibrary')
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Source Information</h5>
                                        <div class="alert alert-info">
                                            <i class="mdi mdi-information"></i> This book was imported from OpenLibrary.
                                            @if($book->openlibrary_id)
                                                <br><strong>OpenLibrary ID:</strong> {{ $book->openlibrary_id }}
                                            @endif
                                            @if($book->read_online_link)
                                                <br><a href="{{ $book->read_online_link }}" target="_blank" class="alert-link">View on OpenLibrary</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-4">
                                <!-- Current File -->
                                @if($book->source == 'local' && $book->file_path)
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Current File</h5>
                                        
                                        <div class="alert alert-success">
                                            <i class="mdi mdi-file-document"></i>
                                            <strong>{{ $book->file_type }}</strong>
                                            <br>
                                            <small>Size: {{ number_format($book->file_size / 1024 / 1024, 2) }} MB</small>
                                        </div>

                                        <div class="mb-3">
                                            <label for="file" class="form-label">Replace File (Optional)</label>
                                            <input type="file" class="form-control" id="file" name="file" accept=".pdf,.epub">
                                            <small class="text-muted">Leave empty to keep current file. Max: 50MB</small>
                                        </div>

                                        <div id="filePreview" class="alert alert-warning" style="display: none;">
                                            <strong>New File:</strong>
                                            <br>
                                            <span id="fileName"></span>
                                            <br>
                                            <small id="fileSize"></small>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Cover Image -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Cover Image</h5>
                                        
                                        @if($book->cover_image)
                                        <div class="text-center mb-3">
                                            <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}" class="img-thumbnail" style="max-width: 100%; max-height: 300px;">
                                            <p class="text-muted mt-2">Current Cover</p>
                                        </div>
                                        @endif

                                        <div class="mb-3">
                                            <label for="cover_image" class="form-label">{{ $book->cover_image ? 'Replace' : 'Upload' }} Cover</label>
                                            <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
                                            <small class="text-muted">{{ $book->cover_image ? 'Leave empty to keep current cover.' : '' }} Max: 5MB</small>
                                        </div>

                                        <div id="coverPreview" class="text-center" style="display: none;">
                                            <img id="coverImage" src="" alt="New Cover Preview" class="img-thumbnail" style="max-width: 100%; max-height: 300px;">
                                            <p class="text-muted mt-2">New Cover Preview</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Options -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Options</h5>

                                        @if($book->source == 'local')
                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="is_downloadable" name="is_downloadable" value="1" {{ old('is_downloadable', $book->is_downloadable) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="is_downloadable">
                                                Allow Downloads
                                            </label>
                                        </div>
                                        @endif

                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="featured" name="featured" value="1" {{ old('featured', $book->featured) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="featured">
                                                Mark as Featured
                                            </label>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="status" name="status" value="1" {{ old('status', $book->status) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="status">
                                                Active (Visible to users)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistics -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Statistics</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="mdi mdi-eye text-info"></i>
                                                <strong>Views:</strong> {{ number_format($book->views_count) }}
                                            </li>
                                            <li class="mb-2">
                                                <i class="mdi mdi-download text-success"></i>
                                                <strong>Downloads:</strong> {{ number_format($book->downloads_count) }}
                                            </li>
                                            <li class="mb-2">
                                                <i class="mdi mdi-heart text-danger"></i>
                                                <strong>Favorites:</strong> {{ number_format($book->favorites_count) }}
                                            </li>
                                            <li class="mb-2">
                                                <i class="mdi mdi-star text-warning"></i>
                                                <strong>Rating:</strong> {{ $book->rating_avg > 0 ? number_format($book->rating_avg, 1) . ' (' . $book->rating_count . ' reviews)' : 'No ratings yet' }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Submit Buttons -->
                                <div class="card">
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-gradient-primary btn-lg w-100 mb-2">
                                            <i class="mdi mdi-content-save"></i> Update Book
                                        </button>
                                        <a href="{{ route('admin.e-library.show', $book->id) }}" class="btn btn-info btn-lg w-100 mb-2">
                                            <i class="mdi mdi-eye"></i> View Details
                                        </a>
                                        <a href="{{ route('admin.e-library.index') }}" class="btn btn-light btn-lg w-100">
                                            <i class="mdi mdi-close"></i> Cancel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// File preview
document.getElementById('file')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = `Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`;
        document.getElementById('filePreview').style.display = 'block';
        
        // Validate file size
        if (file.size > 50 * 1024 * 1024) {
            alert('File size exceeds 50MB limit');
            e.target.value = '';
            document.getElementById('filePreview').style.display = 'none';
        }
    }
});

// Cover image preview
document.getElementById('cover_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('coverImage').src = event.target.result;
            document.getElementById('coverPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
        
        // Validate file size
        if (file.size > 5 * 1024 * 1024) {
            alert('Cover image size exceeds 5MB limit');
            e.target.value = '';
            document.getElementById('coverPreview').style.display = 'none';
        }
    }
});

// Form validation
document.getElementById('bookEditForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
});
</script>
@endpush
@endsection
