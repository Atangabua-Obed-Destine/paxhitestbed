@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.notes-header {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.note-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.note-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.note-subject {
    font-size: 0.85rem;
    color: #667eea;
    font-weight: 600;
    margin-bottom: 5px;
}

.note-date {
    font-size: 0.8rem;
    color: #999;
}

.note-content {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    font-size: 0.95rem;
    line-height: 1.6;
    white-space: pre-wrap;
}

.note-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.filter-section {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.export-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
}

.export-btn:hover {
    color: white;
    opacity: 0.9;
}

.notes-stats {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.stat-item {
    background: rgba(255,255,255,0.2);
    padding: 10px 20px;
    border-radius: 10px;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.8rem;
    opacity: 0.8;
}

.empty-notes {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.empty-notes i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 20px;
}
</style>
@endsection

@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <!-- Header -->
        <div class="notes-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="fas fa-sticky-note mr-3"></i>{{ $title }}</h2>
                    <p class="mb-0 opacity-75">All your personal notes from class sessions</p>
                    <div class="notes-stats">
                        <div class="stat-item">
                            <div class="stat-number">{{ $notes->total() }}</div>
                            <div class="stat-label">Total Notes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">{{ $subjectCount ?? 0 }}</div>
                            <div class="stat-label">Subjects</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{{ route('student.class-hub.index') }}" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Class Hub
                    </a>
                    @if($notes->count() > 0)
                    <a href="{{ route('student.class-hub.export-notes') }}" class="btn export-btn btn-sm ml-2">
                        <i class="fas fa-download mr-1"></i> Export All
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="{{ route('student.class-hub.my-notes') }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label><i class="fas fa-book mr-1"></i> Filter by Subject</label>
                            <select name="subject_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Subjects</option>
                                @foreach($subjects ?? [] as $subject)
                                <option value="{{ $subject->id }}" {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->code }} - {{ $subject->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label><i class="fas fa-search mr-1"></i> Search Notes</label>
                            <input type="text" name="search" class="form-control" placeholder="Search in your notes..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label><i class="fas fa-sort mr-1"></i> Sort By</label>
                            <select name="sort" class="form-control" onchange="this.form.submit()">
                                <option value="newest" {{ request('sort', 'newest') == 'newest' ? 'selected' : '' }}>Newest First</option>
                                <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Oldest First</option>
                                <option value="subject" {{ request('sort') == 'subject' ? 'selected' : '' }}>By Subject</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Notes List -->
        @forelse($notes as $note)
        <div class="note-card">
            <div class="row">
                <div class="col-md-10">
                    <div class="note-subject">
                        <i class="fas fa-book-open mr-1"></i>
                        {{ $note->classSession->subject->code ?? 'N/A' }} - {{ $note->classSession->subject->title ?? 'Unknown' }}
                    </div>
                    <div class="note-date">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        Class Date: {{ $note->classSession->date ? $note->classSession->date->format('M d, Y') : 'N/A' }}
                        &bull;
                        <i class="fas fa-clock mr-1"></i>
                        Note Updated: {{ $note->updated_at->format('M d, Y H:i') }}
                    </div>
                    @if($note->classSession->topic_covered)
                    <div class="mt-2">
                        <small class="text-muted">
                            <strong>Topic:</strong> {{ $note->classSession->topic_covered }}
                        </small>
                    </div>
                    @endif
                </div>
                <div class="col-md-2 text-right">
                    <small class="text-muted">
                        {{ strlen($note->content) }} chars
                    </small>
                </div>
            </div>
            
            <div class="note-content">{{ Str::limit($note->content, 500) }}</div>
            
            <div class="note-actions">
                <a href="{{ route('student.class-hub.live-room', $note->class_session_id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-external-link-alt mr-1"></i> View Class Session
                </a>
                <button class="btn btn-outline-secondary btn-sm ml-2" onclick="copyNoteContent({{ $note->id }})">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
                <button class="btn btn-outline-danger btn-sm ml-2" onclick="confirmDeleteNote({{ $note->id }})">
                    <i class="fas fa-trash mr-1"></i> Delete
                </button>
            </div>
            
            <!-- Hidden full content for copy -->
            <textarea id="note-content-{{ $note->id }}" style="position:absolute;left:-9999px;">{{ $note->content }}</textarea>
        </div>
        @empty
        <div class="empty-notes">
            <i class="fas fa-sticky-note"></i>
            <h4>No Notes Yet</h4>
            <p class="text-muted">Start taking notes in your live class sessions. Your notes will appear here for easy reference.</p>
            <a href="{{ route('student.class-hub.index') }}" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-right mr-1"></i> Go to Today's Classes
            </a>
        </div>
        @endforelse

        <!-- Pagination -->
        @if($notes->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $notes->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Note</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this note? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteNoteForm" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Note</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script>
function copyNoteContent(noteId) {
    const textarea = document.getElementById('note-content-' + noteId);
    const content = textarea.value;
    
    navigator.clipboard.writeText(content).then(function() {
        toastr.success('Note copied to clipboard!');
    }).catch(function() {
        // Fallback
        textarea.style.position = 'fixed';
        textarea.style.left = '0';
        textarea.focus();
        textarea.select();
        document.execCommand('copy');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        toastr.success('Note copied to clipboard!');
    });
}

function confirmDeleteNote(noteId) {
    const form = document.getElementById('deleteNoteForm');
    form.action = '{{ url("student/class-hub/notes") }}/' + noteId;
    $('#deleteNoteModal').modal('show');
}
</script>
@endsection
