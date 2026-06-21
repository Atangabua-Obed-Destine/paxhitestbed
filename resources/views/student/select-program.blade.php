@extends('student.layouts.master')
@section('title', $title)

@section('page_css')
<style>
    .program-selector-container {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .program-selector-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        max-width: 1000px;
        width: 100%;
        overflow: hidden;
    }

    .program-selector-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 30px;
        text-align: center;
    }

    .program-selector-header h2 {
        margin: 0 0 10px 0;
        font-size: 32px;
        font-weight: 700;
    }

    .program-selector-header p {
        margin: 0;
        font-size: 16px;
        opacity: 0.9;
    }

    .program-selector-body {
        padding: 40px 30px;
    }

    .program-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .program-card {
        border: 2px solid #e2e8f0;
        border-radius: 15px;
        padding: 25px;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        background: white;
    }

    .program-card:hover {
        border-color: #667eea;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        transform: translateY(-5px);
    }

    .program-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    }

    .program-card.selected::after {
        content: '\2713';
        position: absolute;
        top: 15px;
        right: 15px;
        background: #667eea;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .program-level-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .program-level-badge.undergraduate {
        background: #10b981;
        color: white;
    }

    .program-level-badge.masters {
        background: #f59e0b;
        color: white;
    }

    .program-level-badge.doctoral {
        background: #8b5cf6;
        color: white;
    }

    .program-status-badges {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-badge i {
        margin-right: 5px;
        font-size: 10px;
    }

    .status-badge.active {
        background: #10b981;
        color: white;
    }

    .status-badge.inactive {
        background: #6b7280;
        color: white;
    }

    .status-badge.graduated {
        background: #3b82f6;
        color: white;
    }

    .status-badge.eligible {
        background: #f59e0b;
        color: white;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.8;
        }
    }

    .program-card-title {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 15px 0;
        line-height: 1.4;
    }

    .program-card-details {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .program-card-detail {
        display: flex;
        align-items: center;
        font-size: 14px;
        color: #64748b;
    }

    .program-card-detail i {
        width: 20px;
        margin-right: 8px;
        color: #667eea;
    }

    .program-card-matricule {
        font-size: 16px;
        font-weight: 600;
        color: #667eea;
        margin-top: 10px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }

    .select-btn {
        width: 100%;
        margin-top: 15px;
        padding: 12px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .select-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .select-btn:disabled {
        opacity: 0.6;
        cursor: not-started;
    }

    .student-info-card {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        text-align: center;
    }

    .student-name {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .student-email {
        color: #64748b;
        font-size: 14px;
    }
</style>
@endsection

@section('content')

<div class="program-selector-container">
    <div class="program-selector-card">
        <div class="program-selector-header">
            <h2><i class="fas fa-graduation-cap"></i> Select Your Program</h2>
            <p>Choose which program enrollment you want to access</p>
        </div>

        <div class="program-selector-body">
            <!-- Student Info -->
            <div class="student-info-card">
                <div class="student-name">{{ $student->first_name }} {{ $student->last_name }}</div>
                <div class="student-email">{{ $student->email }}</div>
            </div>

            <!-- Program Cards -->
            <div class="program-cards-grid">
                @foreach($enrollments as $enrollment)
                <div class="program-card {{ $enrollment->id == $selected_enrollment_id ? 'selected' : '' }}" 
                     data-enrollment-id="{{ $enrollment->id }}">
                    
                    <!-- Level Badge -->
                    @php
                        $level = $enrollment->program->academic_level ?? 'A';
                        $levelName = $enrollment->program->academic_level_name ?? 'Undergraduate';
                        $levelClass = strtolower($levelName);
                        
                        // Check graduation eligibility for active enrollments
                        $isEligibleForGraduation = false;
                        if ($enrollment->status == '1') {
                            $graduationService = app(\App\Services\GraduationEligibilityService::class);
                            $eligibility = $graduationService->checkEligibility($student, $enrollment->program_id);
                            $isEligibleForGraduation = $eligibility['is_eligible'];
                        }
                    @endphp
                    <span class="program-level-badge {{ $levelClass }}">
                        {{ $levelName }}
                    </span>

                    <!-- Status Badges -->
                    <div class="program-status-badges">
                        @if($enrollment->status == '0')
                            @php
                                // For inactive enrollments, check if they actually graduated
                                $meetsGraduationRequirements = false;
                                if ($enrollment->status == '0') {
                                    $graduationService = app(\App\Services\GraduationEligibilityService::class);
                                    $eligibility = $graduationService->checkEligibility($student, $enrollment->program_id);
                                    $meetsGraduationRequirements = $eligibility['is_eligible'];
                                }
                            @endphp
                            @if($meetsGraduationRequirements)
                                <span class="status-badge graduated">
                                    <i class="fas fa-graduation-cap"></i> Graduated
                                </span>
                            @else
                                <span class="status-badge inactive">
                                    <i class="fas fa-archive"></i> Inactive
                                </span>
                            @endif
                        @elseif($isEligibleForGraduation)
                            <span class="status-badge eligible">
                                <i class="fas fa-check-double"></i> Eligible for Graduation
                            </span>
                        @else
                            <span class="status-badge active">
                                <i class="fas fa-circle"></i> Active
                            </span>
                        @endif
                    </div>

                    <!-- Program Title -->
                    <h3 class="program-card-title">{{ $enrollment->program->title ?? 'Unknown Program' }}</h3>

                    <!-- Program Details -->
                    <div class="program-card-details">
                        <div class="program-card-detail">
                            <i class="fas fa-university"></i>
                            <span>{{ $enrollment->program->faculty->title ?? 'N/A' }}</span>
                        </div>

                        <div class="program-card-detail">
                            <i class="fas fa-book-open"></i>
                            <span>{{ $enrollment->semester->title ?? 'N/A' }}</span>
                        </div>

                        <div class="program-card-detail">
                            <i class="fas fa-calendar-alt"></i>
                            <span>{{ $enrollment->session->title ?? 'N/A' }}</span>
                        </div>

                        @if($enrollment->program->degreeType)
                        <div class="program-card-detail">
                            <i class="fas fa-award"></i>
                            <span>{{ $enrollment->program->degreeType->title }}</span>
                        </div>
                        @endif
                    </div>

                    <!-- Matricule -->
                    <div class="program-card-matricule">
                        <i class="fas fa-id-card"></i> Matricule: {{ $enrollment->matricule }}
                    </div>

                    <!-- Select Button -->
                    <form action="{{ route('student.switch-program') }}" method="POST" class="program-select-form">
                        @csrf
                        <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                        <button type="submit" class="select-btn">
                            @if($enrollment->id == $selected_enrollment_id)
                                <i class="fas fa-check"></i> Currently Selected
                            @else
                                <i class="fas fa-arrow-right"></i> Select This Program
                            @endif
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script>
    $(document).ready(function() {
        // Add click handler to cards
        $('.program-card').on('click', function(e) {
            // Don't trigger if clicking the button directly
            if ($(e.target).closest('.select-btn').length === 0) {
                $(this).find('.select-btn').click();
            }
        });

        // Handle form submission with AJAX
        $('.program-select-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $form.find('.select-btn');
            const originalText = $btn.html();
            
            // Disable button and show loading
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Switching...');
            
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    // Redirect to dashboard
                    window.location.href = '{{ route("student.dashboard.index") }}';
                },
                error: function(xhr) {
                    // Re-enable button
                    $btn.prop('disabled', false).html(originalText);
                    
                    // Show error message
                    const message = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Error switching program. Please try again.';
                    
                    alert(message);
                }
            });
        });
    });
</script>
@endsection
