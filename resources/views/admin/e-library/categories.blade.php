@extends('admin.layouts.master')

@section('title', 'Book Categories')

@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            <span class="page-title-icon bg-gradient-primary text-white me-2">
                <i class="mdi mdi-tag-multiple"></i>
            </span> Book Categories
        </h3>
        <nav aria-label="breadcrumb">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.e-library.index') }}">E-Library</a></li>
                <li class="breadcrumb-item active" aria-current="page">Categories</li>
            </ul>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="card-title mb-0">Manage Book Categories</h4>
                        <button type="button" class="btn btn-gradient-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openAddModal()">
                            <i class="mdi mdi-plus"></i> Add New Category
                        </button>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="mdi mdi-information"></i> 
                        <strong>Note:</strong> Categories organize books in the E-Library. 
                        Each category has a unique icon and color theme for easy identification.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th width="60">Icon</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th width="100">Color</th>
                                    <th width="100">Books Count</th>
                                    <th width="100">Sort Order</th>
                                    <th width="100">Status</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $index => $category)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="text-center" style="font-size: 24px; color: {{ $category->color }}">
                                            <i class="{{ $category->icon }}"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $category->name }}</strong>
                                        @if($category->description)
                                        <br><small class="text-muted">{{ Str::limit($category->description, 80) }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $category->slug }}</code></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div style="width: 30px; height: 30px; background: {{ $category->color }}; border-radius: 4px; border: 1px solid #ddd;"></div>
                                            <small class="ms-2">{{ $category->color }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-white">
                                            {{ $category->books_count ?? 0 }} books
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary text-white">{{ $category->sort_order }}</span>
                                    </td>
                                    <td>
                                        @if($category->status == 1)
                                            <span class="badge bg-success text-white">Active</span>
                                        @else
                                            <span class="badge bg-secondary text-white">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-gradient-info" onclick="openEditModal({{ $category->id }}, '{{ $category->name }}', '{{ $category->slug }}', '{{ addslashes($category->description ?? '') }}', '{{ $category->icon }}', '{{ $category->color }}', {{ $category->sort_order }}, {{ $category->status }})">
                                            <i class="mdi mdi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-gradient-danger" onclick="deleteCategory({{ $category->id }}, '{{ $category->name }}', {{ $category->books_count ?? 0 }})">
                                            <i class="mdi mdi-delete"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="mdi mdi-alert-circle-outline mdi-48px text-muted mb-3"></i>
                                        <p class="text-muted">No categories found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mb-3">Category Statistics</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0 text-white">{{ $categories->count() }}</h3>
                                                <small class="text-white">Total Categories</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0 text-white">{{ $categories->where('status', 1)->count() }}</h3>
                                                <small class="text-white">Active Categories</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0 text-white">{{ $categories->sum('books_count') ?? 0 }}</h3>
                                                <small class="text-white">Total Books</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0 text-white" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">{{ $categories->where('books_count', '>', 0)->count() }}</h3>
                                                <small class="text-white" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">Categories with Books</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 alert alert-light">
                        <h6><i class="mdi mdi-lightbulb"></i> Available Categories</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($categories as $category)
                            <span class="badge" style="background: {{ $category->color }}; padding: 8px 12px; font-size: 14px;">
                                <i class="{{ $category->icon }}"></i> {{ $category->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="categoryForm">
                @csrf
                <input type="hidden" id="category_id" name="category_id">
                <input type="hidden" id="form_method" value="POST">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="slug" name="slug" required>
                        <small class="text-muted">Auto-generated from name, or enter custom slug (lowercase, hyphens only)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="icon" class="form-label">Icon <span class="text-danger">*</span></label>
                            <select class="form-select" id="icon" name="icon" required>
                                <option value="">-- Select Icon --</option>
                                <optgroup label="Books & Reading">
                                    <option value="mdi mdi-book">📖 Book</option>
                                    <option value="mdi mdi-book-open-page-variant">📖 Book Open</option>
                                    <option value="mdi mdi-book-multiple">📚 Books Multiple</option>
                                    <option value="mdi mdi-bookshelf">📚 Bookshelf</option>
                                    <option value="mdi mdi-library">🏛️ Library</option>
                                    <option value="mdi mdi-notebook">📓 Notebook</option>
                                    <option value="mdi mdi-notebook-outline">📓 Notebook Outline</option>
                                    <option value="mdi mdi-text-box">📄 Text Box</option>
                                    <option value="mdi mdi-file-document">📄 Document</option>
                                    <option value="mdi mdi-file-pdf-box">📕 PDF</option>
                                </optgroup>
                                <optgroup label="Education & Science">
                                    <option value="mdi mdi-school">🎓 School</option>
                                    <option value="mdi mdi-graduation-cap">🎓 Graduation Cap</option>
                                    <option value="mdi mdi-teach">👨‍🏫 Teach</option>
                                    <option value="mdi mdi-human-male-board">👨‍🏫 Teacher Board</option>
                                    <option value="mdi mdi-flask">🧪 Flask/Science</option>
                                    <option value="mdi mdi-atom">⚛️ Atom</option>
                                    <option value="mdi mdi-dna">🧬 DNA</option>
                                    <option value="mdi mdi-brain">🧠 Brain</option>
                                    <option value="mdi mdi-microscope">🔬 Microscope</option>
                                    <option value="mdi mdi-calculator">🔢 Calculator</option>
                                </optgroup>
                                <optgroup label="Technology & Computing">
                                    <option value="mdi mdi-laptop">💻 Laptop</option>
                                    <option value="mdi mdi-desktop-classic">🖥️ Desktop</option>
                                    <option value="mdi mdi-code-tags">💻 Code</option>
                                    <option value="mdi mdi-database">🗄️ Database</option>
                                    <option value="mdi mdi-server">🖥️ Server</option>
                                    <option value="mdi mdi-web">🌐 Web</option>
                                    <option value="mdi mdi-cellphone">📱 Mobile</option>
                                    <option value="mdi mdi-chip">🔲 Chip</option>
                                    <option value="mdi mdi-robot">🤖 Robot/AI</option>
                                    <option value="mdi mdi-cloud">☁️ Cloud</option>
                                </optgroup>
                                <optgroup label="Business & Finance">
                                    <option value="mdi mdi-briefcase">💼 Briefcase</option>
                                    <option value="mdi mdi-chart-line">📈 Chart Line</option>
                                    <option value="mdi mdi-chart-bar">📊 Chart Bar</option>
                                    <option value="mdi mdi-cash">💵 Cash</option>
                                    <option value="mdi mdi-bank">🏦 Bank</option>
                                    <option value="mdi mdi-currency-usd">💲 Currency</option>
                                    <option value="mdi mdi-account-tie">👔 Business Person</option>
                                    <option value="mdi mdi-handshake">🤝 Handshake</option>
                                </optgroup>
                                <optgroup label="Arts & Humanities">
                                    <option value="mdi mdi-palette">🎨 Palette/Art</option>
                                    <option value="mdi mdi-music">🎵 Music</option>
                                    <option value="mdi mdi-theater">🎭 Theater</option>
                                    <option value="mdi mdi-camera">📷 Camera</option>
                                    <option value="mdi mdi-movie">🎬 Movie</option>
                                    <option value="mdi mdi-pen">🖊️ Pen</option>
                                    <option value="mdi mdi-brush">🖌️ Brush</option>
                                    <option value="mdi mdi-history">📜 History</option>
                                    <option value="mdi mdi-translate">🌍 Language</option>
                                </optgroup>
                                <optgroup label="Health & Medicine">
                                    <option value="mdi mdi-medical-bag">🏥 Medical Bag</option>
                                    <option value="mdi mdi-hospital-building">🏥 Hospital</option>
                                    <option value="mdi mdi-pill">💊 Pill</option>
                                    <option value="mdi mdi-stethoscope">🩺 Stethoscope</option>
                                    <option value="mdi mdi-heart-pulse">❤️ Heart Pulse</option>
                                    <option value="mdi mdi-tooth">🦷 Tooth/Dental</option>
                                    <option value="mdi mdi-eye">👁️ Eye</option>
                                </optgroup>
                                <optgroup label="Law & Government">
                                    <option value="mdi mdi-gavel">⚖️ Gavel/Law</option>
                                    <option value="mdi mdi-scale-balance">⚖️ Scale/Justice</option>
                                    <option value="mdi mdi-shield">🛡️ Shield</option>
                                    <option value="mdi mdi-city">🏙️ City</option>
                                    <option value="mdi mdi-domain">🏛️ Domain/Building</option>
                                </optgroup>
                                <optgroup label="Nature & Environment">
                                    <option value="mdi mdi-leaf">🌿 Leaf</option>
                                    <option value="mdi mdi-tree">🌳 Tree</option>
                                    <option value="mdi mdi-earth">🌍 Earth</option>
                                    <option value="mdi mdi-weather-sunny">☀️ Sun</option>
                                    <option value="mdi mdi-water">💧 Water</option>
                                    <option value="mdi mdi-flower">🌸 Flower</option>
                                </optgroup>
                                <optgroup label="Religion & Philosophy">
                                    <option value="mdi mdi-cross">✝️ Cross</option>
                                    <option value="mdi mdi-church">⛪ Church</option>
                                    <option value="mdi mdi-book-cross">📖 Bible</option>
                                    <option value="mdi mdi-star-crescent">☪️ Islam</option>
                                    <option value="mdi mdi-meditation">🧘 Meditation</option>
                                    <option value="mdi mdi-thought-bubble">💭 Philosophy</option>
                                </optgroup>
                                <optgroup label="Sports & Recreation">
                                    <option value="mdi mdi-soccer">⚽ Soccer</option>
                                    <option value="mdi mdi-basketball">🏀 Basketball</option>
                                    <option value="mdi mdi-football">🏈 Football</option>
                                    <option value="mdi mdi-run">🏃 Running</option>
                                    <option value="mdi mdi-dumbbell">🏋️ Fitness</option>
                                    <option value="mdi mdi-trophy">🏆 Trophy</option>
                                </optgroup>
                                <optgroup label="Other">
                                    <option value="mdi mdi-star">⭐ Star</option>
                                    <option value="mdi mdi-lightbulb">💡 Lightbulb/Idea</option>
                                    <option value="mdi mdi-puzzle">🧩 Puzzle</option>
                                    <option value="mdi mdi-cog">⚙️ Settings</option>
                                    <option value="mdi mdi-folder">📁 Folder</option>
                                    <option value="mdi mdi-tag">🏷️ Tag</option>
                                    <option value="mdi mdi-magnify">🔍 Search</option>
                                    <option value="mdi mdi-help-circle">❓ Help</option>
                                </optgroup>
                            </select>
                            <div class="mt-2" id="icon-preview">
                                <span class="text-muted">Preview: </span>
                                <i id="icon-preview-display" class="" style="font-size: 24px;"></i>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#007bff" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sort_order" class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gradient-primary">
                        <i class="mdi mdi-content-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('style')
<style>
    .badge {
        margin: 4px;
    }
    .gap-2 {
        gap: 8px !important;
    }
    /* Ensure text visibility on gradient cards */
    .card.bg-gradient-primary .card-body,
    .card.bg-gradient-success .card-body,
    .card.bg-gradient-info .card-body,
    .card.bg-gradient-warning .card-body,
    .card.bg-gradient-danger .card-body {
        text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }
    /* Make sure warning card text is visible */
    .card.bg-gradient-warning {
        color: #000 !important;
    }
    .card.bg-gradient-warning .card-body h3,
    .card.bg-gradient-warning .card-body small {
        color: #000 !important;
        text-shadow: 1px 1px 1px rgba(255,255,255,0.5);
    }
    /* Badge outline visibility */
    .badge-outline-secondary {
        border: 1px solid #6c757d;
        color: #6c757d;
    }
    /* Table code text visibility */
    code {
        background-color: #f8f9fa;
        padding: 2px 6px;
        border-radius: 3px;
        color: #e83e8c;
    }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Icon Preview Functionality
function updateIconPreview(iconClass) {
    const preview = document.getElementById('icon-preview-display');
    if(iconClass) {
        preview.className = iconClass;
    } else {
        preview.className = '';
    }
}

// Update preview when icon selection changes
document.getElementById('icon').addEventListener('change', function() {
    updateIconPreview(this.value);
});

// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    if(document.getElementById('form_method').value === 'POST') {
        const slug = this.value.toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-')
            .trim();
        document.getElementById('slug').value = slug;
    }
});

// Open Add Modal
function openAddModal() {
    document.getElementById('categoryModalLabel').textContent = 'Add New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('category_id').value = '';
    document.getElementById('form_method').value = 'POST';
    document.getElementById('color').value = '#007bff';
    updateIconPreview('');
}

// Open Edit Modal
function openEditModal(id, name, slug, description, icon, color, sort_order, status) {
    document.getElementById('categoryModalLabel').textContent = 'Edit Category';
    document.getElementById('category_id').value = id;
    document.getElementById('form_method').value = 'PUT';
    document.getElementById('name').value = name;
    document.getElementById('slug').value = slug;
    document.getElementById('description').value = description;
    document.getElementById('icon').value = icon;
    updateIconPreview(icon);
    document.getElementById('color').value = color;
    document.getElementById('sort_order').value = sort_order;
    document.getElementById('status').value = status;
    
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

// Submit Form
document.getElementById('categoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const method = document.getElementById('form_method').value;
    const categoryId = document.getElementById('category_id').value;
    
    let url = '{{ route("admin.e-library.categories.store") }}';
    if(method === 'PUT') {
        url = '{{ url("admin/e-library/categories") }}/' + categoryId;
    }
    
    // Convert FormData to object
    const data = {};
    formData.forEach((value, key) => {
        if(key !== 'category_id' && key !== 'form_method') {
            data[key] = value;
        }
    });
    
    // Add CSRF token
    data._token = '{{ csrf_token() }}';
    if(method === 'PUT') {
        data._method = 'PUT';
    }
    
    // Show loading
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw err;
            });
        }
        return response.json();
    })
    .then(data => {
        if(data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message || 'Something went wrong!'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        let errorMessage = 'An error occurred while saving the category.';
        
        // Handle validation errors
        if(error.errors) {
            errorMessage = Object.values(error.errors).flat().join('<br>');
        } else if(error.message) {
            errorMessage = error.message;
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            html: errorMessage
        });
    });
});

// Delete Category
function deleteCategory(id, name, booksCount) {
    if(booksCount > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Delete',
            text: `This category has ${booksCount} book(s). Please reassign the books before deleting.`
        });
        return;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to delete the category "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('{{ url("admin/e-library/categories") }}/' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    _method: 'DELETE'
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while deleting the category.'
                });
            });
        }
    });
}
</script>
@endsection
