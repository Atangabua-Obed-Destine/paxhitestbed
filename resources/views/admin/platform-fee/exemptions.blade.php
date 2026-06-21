@extends('admin.layouts.master')
@section('title', 'Platform Fee Exemptions')

@section('content')
<style>
    .exemptions-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .exemption-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 15px;
        transition: all 0.3s ease;
        border-left: 5px solid;
    }
    
    .exemption-card.student {
        border-left-color: #3b82f6;
    }
    
    .exemption-card.session {
        border-left-color: #10b981;
    }
    
    .exemption-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }
    
    .exemption-type-badge {
        padding: 5px 15px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .type-student {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }
    
    .type-session {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .btn-add-exemption {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
    }
    
    .btn-add-exemption:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }
    
    .btn-delete {
        background: #ef4444;
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 8px;
        font-size: 12px;
        transition: all 0.3s ease;
    }
    
    .btn-delete:hover {
        background: #dc2626;
        transform: scale(1.05);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
    }
    
    .exemption-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .exemption-details {
        flex: 1;
    }
    
    .exemption-title {
        font-weight: 600;
        font-size: 16px;
        color: #1e293b;
        margin-bottom: 5px;
    }
    
    .exemption-subtitle {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .exemption-reason {
        background: #f8fafc;
        padding: 10px;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
        margin-top: 10px;
    }
    
    .exemption-meta {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 10px;
    }
</style>

<div class="content-header row">
</div>

<div class="content-body">
    <section id="platform-fee-exemptions">
        <div class="exemptions-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-user-shield"></i> Platform Fee Exemptions</h2>
                    <p class="mb-0 mt-2" style="opacity: 0.9;">Manage fee exemptions for students or entire sessions</p>
                </div>
                <button class="btn btn-add-exemption" data-bs-toggle="modal" data-bs-target="#createExemptionModal">
                    <i class="fas fa-plus"></i> Add Exemption
                </button>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Exemptions List -->
        @if($exemptions->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-shield-alt text-muted" style="font-size: 64px;"></i>
                <h4 class="mt-4 text-muted">No Exemptions Found</h4>
                <p class="text-muted">Click "Add Exemption" to create a new exemption for a student or session</p>
            </div>
        </div>
        @else
        <div class="exemptions-list">
            @foreach($exemptions as $exemption)
            <div class="exemption-card {{ $exemption->exemption_type }}">
                <div class="exemption-info">
                    <div class="exemption-details">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="exemption-type-badge type-{{ $exemption->exemption_type }}">
                                <i class="fas fa-{{ $exemption->exemption_type == 'student' ? 'user' : 'calendar-alt' }}"></i>
                                {{ ucfirst($exemption->exemption_type) }} Exemption
                            </span>
                            @if($exemption->status)
                            <span class="badge bg-success">Active</span>
                            @else
                            <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </div>

                        <div class="exemption-title">
                            @if($exemption->exemption_type == 'student')
                                <i class="fas fa-user-graduate text-primary"></i> 
                                {{ $exemption->studentEnroll->student->first_name ?? '' }} 
                                {{ $exemption->studentEnroll->student->last_name ?? 'N/A' }}
                                <span class="exemption-subtitle">({{ $exemption->studentEnroll->student->student_id ?? 'N/A' }})</span>
                            @else
                                <i class="fas fa-calendar text-success"></i> {{ $exemption->session->title ?? 'N/A' }}
                                <span class="exemption-subtitle">(All students in this session)</span>
                            @endif
                        </div>

                        @if($exemption->reason)
                        <div class="exemption-reason">
                            <i class="fas fa-comment-dots"></i> <strong>Reason:</strong> {{ $exemption->reason }}
                        </div>
                        @endif

                        <div class="exemption-meta">
                            <i class="fas fa-user"></i> Created by {{ $exemption->creator->name ?? 'System' }} 
                            <i class="fas fa-clock ms-3"></i> {{ $exemption->created_at->diffForHumans() }}
                        </div>
                    </div>

                    <div class="exemption-actions">
                        <form action="{{ route('admin.platform-fee.exemptions.delete', $exemption->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this exemption?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $exemptions->links() }}
        </div>
        @endif
    </section>
</div>

<!-- Create Exemption Modal -->
<div class="modal fade" id="createExemptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create New Exemption</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.platform-fee.exemptions.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Exemption Type -->
                    <div class="form-group mb-3">
                        <label class="form-label">Exemption Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="exemption_type" id="exemption_type" required>
                            <option value="">Select Type</option>
                            <option value="student">Student Specific</option>
                            <option value="session">Entire Session</option>
                        </select>
                        <small class="text-muted">Choose to exempt a specific student or all students in a session</small>
                    </div>

                    <!-- Student Selection (Hidden initially) -->
                    <div class="form-group mb-3" id="student_field" style="display:none;">
                        <label class="form-label">Select Student <span class="text-danger">*</span></label>
                        <select class="form-select" name="student_enroll_id" id="student_enroll_id">
                            <option value="">Choose Student</option>
                            @foreach($studentEnrollments as $enrollment)
                                @if($enrollment->student && $enrollment->session)
                                <option value="{{ $enrollment->id }}">
                                    {{ $enrollment->student->first_name }} {{ $enrollment->student->last_name }} 
                                    ({{ $enrollment->student->student_id }}) - 
                                    {{ $enrollment->session->title }}
                                </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <!-- Session Selection (Hidden initially) -->
                    <div class="form-group mb-3" id="session_field" style="display:none;">
                        <label class="form-label">Select Session <span class="text-danger">*</span></label>
                        <select class="form-select" name="session_id" id="session_id">
                            <option value="">Choose Session</option>
                            @foreach($sessions as $session)
                                <option value="{{ $session->id }}">{{ $session->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reason -->
                    <div class="form-group mb-3">
                        <label class="form-label">Reason for Exemption</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Optional: Explain why this exemption is being granted..."></textarea>
                    </div>

                    <!-- Status -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="status" value="1" id="status" checked>
                        <label class="form-check-label" for="status">
                            Active (Exemption is enabled)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Exemption
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Toggle fields based on exemption type
    $('#exemption_type').change(function() {
        var type = $(this).val();
        
        if(type == 'student') {
            $('#student_field').show();
            $('#student_enroll_id').attr('required', true);
            $('#session_field').hide();
            $('#session_id').attr('required', false).val('');
        } else if(type == 'session') {
            $('#session_field').show();
            $('#session_id').attr('required', true);
            $('#student_field').hide();
            $('#student_enroll_id').attr('required', false).val('');
        } else {
            $('#student_field, #session_field').hide();
            $('#student_enroll_id, #session_id').attr('required', false).val('');
        }
    });
});
</script>
@endpush
