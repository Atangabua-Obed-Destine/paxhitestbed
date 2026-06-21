@extends('student.layouts.master')

@section('title', 'Read: ' . $book->title)

@section('content')
<style>
body {
    overflow: hidden;
}

.reader-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #f5f5f5;
    z-index: 9999;
}

.reader-header {
    background: #fff;
    border-bottom: 1px solid #ddd;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.reader-content {
    position: absolute;
    top: 70px;
    left: 0;
    right: 0;
    bottom: 70px;
    overflow: auto;
    background: #fff;
}

.reader-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    border-top: 1px solid #ddd;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
}

.pdf-viewer {
    width: 100%;
    height: 100%;
    border: none;
}

.epub-viewer {
    width: 100%;
    height: 100%;
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
    font-size: 18px;
    line-height: 1.8;
}

.page-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.progress-bar-container {
    flex: 1;
    max-width: 300px;
}

.fullscreen-btn {
    cursor: pointer;
}

.book-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.book-info img {
    width: 40px;
    height: 55px;
    object-fit: cover;
    border-radius: 4px;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.zoom-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 768px) {
    .reader-header, .reader-footer {
        padding: 10px;
    }
    
    .book-info span {
        display: none;
    }
    
    .progress-bar-container {
        max-width: 150px;
    }
}
</style>

<div class="reader-wrapper">
    <!-- Header -->
    <div class="reader-header">
        <div class="book-info">
            @if($book->cover_image)
                <img src="{{ asset('uploads/e-library/covers/' . $book->cover_image) }}" alt="{{ $book->title }}">
            @endif
            <div>
                <h6 class="mb-0">{{ Str::limit($book->title, 40) }}</h6>
                <small class="text-muted">{{ $book->authors_list }}</small>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="zoom-controls d-none d-md-flex">
                <button class="btn btn-sm btn-outline-secondary" onclick="zoomOut()" title="Zoom Out">
                    <i class="mdi mdi-minus"></i>
                </button>
                <span id="zoomLevel">100%</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="zoomIn()" title="Zoom In">
                    <i class="mdi mdi-plus"></i>
                </button>
            </div>
            
            <button class="btn btn-sm btn-outline-primary fullscreen-btn" onclick="toggleFullscreen()" title="Fullscreen">
                <i class="mdi mdi-fullscreen"></i>
            </button>
            
            <a href="{{ route('student.e-library.show', $book->id) }}" class="btn btn-sm btn-danger" title="Exit Reader">
                <i class="mdi mdi-close"></i> Exit
            </a>
        </div>
    </div>

    <!-- Content Area -->
    <div class="reader-content" id="readerContent">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading book...</p>
            </div>
        </div>

        @if($book->source == 'local' && $book->file_path)
            @if($book->file_type == 'PDF')
                <!-- PDF Viewer -->
                <iframe id="pdfViewer" class="pdf-viewer" src="{{ asset('uploads/e-library/books/' . $book->file_path) }}#toolbar=0&navpanes=0&scrollbar=1"></iframe>
            @elseif($book->file_type == 'EPUB')
                <!-- EPUB Viewer -->
                <div id="epubViewer" class="epub-viewer"></div>
            @else
                <div class="text-center py-5">
                    <i class="mdi mdi-alert-circle mdi-72px text-danger"></i>
                    <h5 class="mt-3">Unsupported file format</h5>
                    <p class="text-muted">This file format is not supported for online reading.</p>
                    @if($book->is_downloadable)
                        <a href="{{ route('student.e-library.download', $book->id) }}" class="btn btn-primary mt-3">
                            <i class="mdi mdi-download"></i> Download Book
                        </a>
                    @endif
                </div>
            @endif
        @elseif($book->source == 'internet_archive')
            <!-- Internet Archive Embedded Reader - Fully embeddable! -->
            @if($book->read_online_link)
                <iframe id="archiveReader" 
                        class="pdf-viewer" 
                        src="{{ $book->read_online_link }}" 
                        frameborder="0"
                        webkitallowfullscreen="true" 
                        mozallowfullscreen="true" 
                        allowfullscreen
                        style="width: 100%; height: 100%; border: none;"></iframe>
                
                <!-- Fallback link if iframe doesn't load properly -->
                <div id="archiveFallback" style="display: none; text-align: center; padding: 50px;">
                    <i class="mdi mdi-archive mdi-72px text-primary"></i>
                    <h4 class="mt-3">{{ $book->title }}</h4>
                    <p class="text-muted">If the reader doesn't load, click below to read on Internet Archive</p>
                    <a href="{{ $book->preview_link }}" target="_blank" class="btn btn-lg btn-primary mt-3">
                        <i class="mdi mdi-open-in-new"></i> Open on Internet Archive
                    </a>
                </div>
                
                <script>
                    // Show fallback if iframe fails to load
                    document.getElementById('archiveReader').onerror = function() {
                        document.getElementById('archiveReader').style.display = 'none';
                        document.getElementById('archiveFallback').style.display = 'block';
                    };
                    
                    // Also set a timeout fallback
                    setTimeout(function() {
                        var iframe = document.getElementById('archiveReader');
                        try {
                            // Try to access iframe content - will fail on cross-origin but that's OK for Internet Archive
                            if (iframe && iframe.contentWindow && iframe.contentWindow.document.body.innerHTML === '') {
                                document.getElementById('archiveFallback').style.display = 'block';
                            }
                        } catch(e) {
                            // Cross-origin - iframe is loading fine
                        }
                    }, 5000);
                </script>
            @else
                <div class="text-center py-5">
                    <i class="mdi mdi-archive mdi-72px text-primary"></i>
                    <h4 class="mt-3">{{ $book->title }}</h4>
                    <p class="text-muted">Click below to read on Internet Archive</p>
                    <a href="{{ $book->preview_link }}" target="_blank" class="btn btn-lg btn-primary mt-3">
                        <i class="mdi mdi-open-in-new"></i> Open on Internet Archive
                    </a>
                </div>
            @endif
        @elseif($book->source == 'google_books')
            <!-- Google Books Embedded Viewer -->
            <div id="googleBooksViewerContainer" style="width: 100%; height: 100%;">
                <div id="viewerCanvas" style="width: 100%; height: 100%;"></div>
            </div>
            
            <!-- Fallback if viewer fails -->
            <div id="googleBooksFallback" style="display: none;">
                <div class="text-center py-5">
                    <div class="mb-4">
                        @if($book->cover_image)
                            <img src="{{ $book->cover_image }}" alt="{{ $book->title }}" style="max-height: 200px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        @else
                            <i class="mdi mdi-google mdi-72px text-primary"></i>
                        @endif
                    </div>
                    <h4 class="mt-3">{{ $book->title }}</h4>
                    <p class="text-muted mb-2">Preview not available for embedding</p>
                    <p class="text-muted small mb-4">
                        <i class="mdi mdi-information-outline"></i> 
                        This book's preview is restricted. You can view it directly on Google Books.
                    </p>
                    <div class="btn-group">
                        @if($book->read_online_link)
                            <a href="{{ $book->read_online_link }}" target="_blank" class="btn btn-lg btn-primary">
                                <i class="mdi mdi-book-open-page-variant"></i> Read on Google Play Books
                            </a>
                        @endif
                        @if($book->preview_link)
                            <a href="{{ $book->preview_link }}" target="_blank" class="btn btn-lg btn-outline-primary">
                                <i class="mdi mdi-eye"></i> Preview on Google Books
                            </a>
                        @endif
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('student.e-library.show', $book->id) }}" class="btn btn-secondary">
                            <i class="mdi mdi-arrow-left"></i> Back to Book Details
                        </a>
                    </div>
                </div>
            </div>
        @elseif(in_array($book->source, ['openlibrary', 'gutenberg']) && $book->read_online_link)
            <!-- OpenLibrary/Gutenberg External Link - These may work in iframe -->
            <iframe class="pdf-viewer" src="{{ $book->read_online_link }}" allow="fullscreen"></iframe>
        @elseif(in_array($book->source, ['openlibrary', 'gutenberg']) && $book->preview_link)
            <!-- Fallback to Preview Link if read_online_link not available -->
            <iframe class="pdf-viewer" src="{{ $book->preview_link }}" allow="fullscreen"></iframe>
        @else
            <div class="text-center py-5">
                <i class="mdi mdi-book-remove mdi-72px text-muted"></i>
                <h5 class="mt-3">Book not available for online reading</h5>
                <p class="text-muted">This book cannot be read online at the moment.</p>
                <a href="{{ route('student.e-library.show', $book->id) }}" class="btn btn-secondary mt-3">
                    <i class="mdi mdi-arrow-left"></i> Back to Book Details
                </a>
            </div>
        @endif
    </div>

    <!-- Footer Controls -->
    <div class="reader-footer">
        <div class="page-controls">
            <button class="btn btn-sm btn-outline-primary" onclick="previousPage()" id="prevBtn">
                <i class="mdi mdi-chevron-left"></i> Previous
            </button>
            <span id="pageInfo">Page <span id="currentPage">1</span> of <span id="totalPages">--</span></span>
            <button class="btn btn-sm btn-outline-primary" onclick="nextPage()" id="nextBtn">
                Next <i class="mdi mdi-chevron-right"></i>
            </button>
        </div>

        <div class="progress-bar-container">
            <div class="d-flex justify-content-between mb-1">
                <small>Progress</small>
                <small id="progressPercent">0%</small>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" id="progressBar" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="addBookmark()" title="Bookmark">
                <i class="mdi mdi-bookmark-outline"></i>
            </button>
        </div>
    </div>
</div>

@if($book->source == 'google_books')
<!-- Google Books Embedded Viewer API -->
<script type="text/javascript" src="https://www.google.com/books/jsapi.js"></script>
<script type="text/javascript">
    var googleBooksId = '{{ $book->google_books_id }}';
    var googleBooksIsbn = '{{ $book->isbn ?? "" }}';
    var viewerLoaded = false;
    var loadingTimeout;
    
    // Set a timeout to show fallback if loading takes too long
    loadingTimeout = setTimeout(function() {
        if (!viewerLoaded) {
            console.log('Loading timeout - showing fallback');
            document.getElementById('loadingOverlay').style.display = 'none';
            showFallback();
        }
    }, 8000); // 8 second timeout
    
    try {
        google.books.load();
        
        google.books.setOnLoadCallback(function() {
            // Hide loading overlay
            document.getElementById('loadingOverlay').style.display = 'none';
            
            var viewerCanvas = document.getElementById('viewerCanvas');
            if (!viewerCanvas) {
                console.error('Viewer canvas not found');
                showFallback();
                return;
            }
            
            var viewer = new google.books.DefaultViewer(viewerCanvas);
            
            // Try to load by Google Books ID first
            viewer.load(googleBooksId, alertNotFound, successCallback);
            
            function alertNotFound() {
                console.log('Could not load book by ID: ' + googleBooksId);
                
                if (googleBooksIsbn) {
                    console.log('Trying ISBN: ' + googleBooksIsbn);
                    viewer.load('ISBN:' + googleBooksIsbn, function() {
                        console.log('ISBN also failed');
                        showFallback();
                    }, successCallback);
                } else {
                    showFallback();
                }
            }
            
            function successCallback() {
                viewerLoaded = true;
                clearTimeout(loadingTimeout);
                console.log('Book loaded successfully');
            }
        });
    } catch (e) {
        console.error('Google Books API error:', e);
        document.getElementById('loadingOverlay').style.display = 'none';
        showFallback();
    }
    
    function showFallback() {
        clearTimeout(loadingTimeout);
        var container = document.getElementById('googleBooksViewerContainer');
        var fallback = document.getElementById('googleBooksFallback');
        if (container) container.style.display = 'none';
        if (fallback) fallback.style.display = 'block';
    }
</script>
@endif

@push('scripts')
<script>
const bookId = {{ $book->id }};
const bookType = '{{ $book->file_type ?? "unknown" }}';
const bookSource = '{{ $book->source ?? "local" }}';
const totalPages = {{ $book->number_of_pages ?? 0 }};
let currentPage = 1;
let zoom = 100;
let updateProgressTimer;

// Initialize reader
document.addEventListener('DOMContentLoaded', function() {
    // Hide loading overlay for all external sources (including internet_archive)
    if (bookSource !== 'google_books') {
        // For internet_archive, hide immediately since it has its own loading
        if (bookSource === 'internet_archive') {
            document.getElementById('loadingOverlay').style.display = 'none';
        } else {
            setTimeout(() => {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 1500);
        }
    }

    // Load saved progress
    loadProgress();

    // Auto-save progress every 30 seconds
    setInterval(saveProgress, 30000);

    // Initialize based on book type (for local files)
    if (bookSource === 'local') {
        if (bookType === 'PDF') {
            initPDFReader();
        } else if (bookType === 'EPUB') {
            initEPUBReader();
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') previousPage();
        if (e.key === 'ArrowRight') nextPage();
        if (e.key === 'Escape') window.location.href = '{{ route("student.e-library.show", $book->id) }}';
    });
});

// Save progress before leaving
window.addEventListener('beforeunload', function() {
    saveProgress();
});

function initPDFReader() {
    // PDF.js integration would go here
    // For now, we're using iframe with basic controls
    if (totalPages > 0) {
        document.getElementById('totalPages').textContent = totalPages;
    }
}

function initEPUBReader() {
    // EPUB.js integration would go here
    // This is a placeholder for EPUB functionality
    document.getElementById('epubViewer').innerHTML = '<p>EPUB reader is being initialized...</p>';
}

function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        updatePageDisplay();
        saveProgress();
    }
}

function nextPage() {
    if (totalPages === 0 || currentPage < totalPages) {
        currentPage++;
        updatePageDisplay();
        saveProgress();
    }
}

function updatePageDisplay() {
    document.getElementById('currentPage').textContent = currentPage;
    
    // Calculate progress
    const progress = totalPages > 0 ? (currentPage / totalPages * 100) : 0;
    document.getElementById('progressBar').style.width = progress + '%';
    document.getElementById('progressPercent').textContent = Math.round(progress) + '%';
    
    // Update button states
    document.getElementById('prevBtn').disabled = currentPage === 1;
    document.getElementById('nextBtn').disabled = totalPages > 0 && currentPage >= totalPages;
}

function zoomIn() {
    zoom += 10;
    if (zoom > 200) zoom = 200;
    applyZoom();
}

function zoomOut() {
    zoom -= 10;
    if (zoom < 50) zoom = 50;
    applyZoom();
}

function applyZoom() {
    document.getElementById('zoomLevel').textContent = zoom + '%';
    const content = document.getElementById('readerContent');
    content.style.transform = `scale(${zoom / 100})`;
    content.style.transformOrigin = 'top center';
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        document.querySelector('.fullscreen-btn i').classList.replace('mdi-fullscreen', 'mdi-fullscreen-exit');
    } else {
        document.exitFullscreen();
        document.querySelector('.fullscreen-btn i').classList.replace('mdi-fullscreen-exit', 'mdi-fullscreen');
    }
}

function loadProgress() {
    fetch(`/student/e-library/book/${bookId}/progress`)
        .then(response => response.json())
        .then(data => {
            if (data.reading) {
                currentPage = data.reading.current_page || 1;
                updatePageDisplay();
            }
        })
        .catch(error => console.error('Error loading progress:', error));
}

function saveProgress() {
    clearTimeout(updateProgressTimer);
    updateProgressTimer = setTimeout(() => {
        fetch(`/student/e-library/book/${bookId}/progress`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                current_page: currentPage,
                total_pages: totalPages || currentPage
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Progress saved:', data);
        })
        .catch(error => console.error('Error saving progress:', error));
    }, 1000);
}

function addBookmark() {
    const bookmarkData = {
        book_id: bookId,
        page: currentPage,
        timestamp: new Date().toISOString()
    };
    
    // Store bookmark in localStorage
    const bookmarks = JSON.parse(localStorage.getItem('bookmarks') || '[]');
    bookmarks.push(bookmarkData);
    localStorage.setItem('bookmarks', JSON.stringify(bookmarks));
    
    alert('Bookmark added at page ' + currentPage);
}

// Update page display on load
updatePageDisplay();
</script>

<!-- PDF.js Library (Optional - for better PDF support) -->
@if($book->file_type == 'PDF')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endif

<!-- EPUB.js Library (Optional - for EPUB support) -->
@if($book->file_type == 'EPUB')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
<script>
// Initialize EPUB.js
if (bookType === 'EPUB') {
    const book = ePub("{{ asset('uploads/e-library/books/' . $book->file_path) }}");
    const rendition = book.renderTo("epubViewer", {
        width: "100%",
        height: "100%",
        spread: "none"
    });
    
    rendition.display();
    
    // Navigation
    rendition.on("relocated", function(location) {
        const progress = book.locations.percentageFromCfi(location.start.cfi);
        currentPage = Math.ceil(progress * totalPages);
        updatePageDisplay();
    });
    
    // Override navigation functions for EPUB
    window.previousPage = function() {
        rendition.prev();
    };
    
    window.nextPage = function() {
        rendition.next();
    };
}
</script>
@endif
@endpush
@endsection
