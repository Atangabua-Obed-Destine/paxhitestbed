@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                        <a href="{{ route($route.'.index', [
                            'faculty' => $selected_faculty,
                            'program' => $selected_program,
                            'session' => $selected_session,
                            'semester' => $selected_semester,
                            'section' => $selected_section,
                            'subject' => $selected_subject,
                            'date' => $selected_date
                        ]) }}" class="btn btn-secondary btn-sm float-right">
                            <i class="fas fa-arrow-left"></i> {{ __('btn_back') }}
                        </a>
                    </div>
                    <div class="card-block text-center">
                        
                        <div class="row mb-4">
                            <div class="col-md-12 text-center">
                                <h4 class="text-primary">{{ $today_date }}</h4>
                                @if($subject_info)
                                <h5 class="text-secondary">{{ $subject_info->code }} - {{ $subject_info->title }}</h5>
                                @endif
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h3>{{ $total_students }}</h3>
                                        <p class="mb-0">Total Students</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-present">{{ $present_count }}</h3>
                                        <p class="mb-0">{{ __('attendance_present') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-absent">{{ $absent_count }}</h3>
                                        <p class="mb-0">{{ __('attendance_absent') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($selected_subject !== '0')
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div id="reader" width="600px"></div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h3 id="scan-result" class="text-muted">Waiting for scan...</h3>
                            <p id="scan-details"></p>
                        </div>

                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Please position the student's ID card QR code in front of the camera.
                            </div>
                        </div>
                        @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No subject selected. Please select a subject from the attendance page first.
                        </div>
                        @endif
                    </div>
                </div>

                @if($selected_subject !== '0')
                <!-- Recent Scans -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Scans</h5>
                    </div>
                    <div class="card-block">
                        <div id="recent-scans">
                            <p class="text-muted text-center">No scans yet</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
@if($selected_subject !== '0')
<script src="{{ asset('dashboard/js/html5-qrcode.min.js') }}"></script>
<script type="text/javascript">
    "use strict";
    
    // Simple debounce - matches staff scanner pattern
    var isScanning = false;
    
    // Persist recent scans
    var recentScans = [];

    function playBeep(success) {
        try {
            var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            var playTone = function(freq, start, duration) {
                var oscillator = audioCtx.createOscillator();
                var gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(freq, start);
                gainNode.gain.setValueAtTime(0, start);
                gainNode.gain.linearRampToValueAtTime(0.3, start + 0.05);
                gainNode.gain.exponentialRampToValueAtTime(0.01, start + duration);
                oscillator.start(start);
                oscillator.stop(start + duration);
            };
            var now = audioCtx.currentTime;
            if (success !== false) {
                playTone(523.25, now, 0.15); 
                playTone(659.25, now + 0.1, 0.3); 
            } else {
                playTone(440, now, 0.15);
                playTone(330, now + 0.1, 0.3);
            }
        } catch (e) {
            console.error("Audio play failed", e);
        }
    }
    
    function showAlert(icon, title, text, timer) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                timer: timer || 2000,
                showConfirmButton: false
            });
        } else {
            console.log(title + ': ' + text);
        }
    }

    function addRecentScan(student, matricule, time, status) {
        recentScans.unshift({ student: student, matricule: matricule, time: time, status: status });
        if (recentScans.length > 50) {
            recentScans.pop();
        }
        updateRecentScansUI();
    }

    function updateRecentScansUI() {
        if (recentScans.length === 0) {
            $('#recent-scans').html('<p class="text-muted text-center">No scans yet</p>');
            return;
        }
        var html = '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;"><table class="table table-sm table-striped"><thead><tr><th>#</th><th>Student</th><th>Matricule</th><th>Time</th><th>Status</th></tr></thead><tbody>';
        recentScans.forEach(function(scan, index) {
            var badgeClass = scan.status === 'success' ? 'badge-success' : (scan.status === 'warning' ? 'badge-warning' : 'badge-danger');
            var statusText = scan.status === 'success' ? 'Present' : (scan.status === 'warning' ? 'Already Marked' : 'Error');
            html += '<tr><td>' + (index + 1) + '</td><td>' + scan.student + '</td><td>' + scan.matricule + '</td><td>' + scan.time + '</td><td><span class="badge ' + badgeClass + '">' + statusText + '</span></td></tr>';
        });
        html += '</tbody></table></div>';
        html += '<p class="text-muted mt-2">Total scans: ' + recentScans.length + '</p>';
        $('#recent-scans').html(html);
    }

    function onScanSuccess(decodedText, decodedResult) {
        // Simple debounce check
        if (isScanning) return;
        isScanning = true;

        console.log('Scan result:', decodedText, decodedResult);

        // Play beep immediately
        playBeep(true);

        // Extract Student ID from URL if it's a URL
        var studentId = decodedText;
        if(typeof decodedText === 'string' && decodedText.includes('/verify-student/')) {
            var parts = decodedText.split('/verify-student/');
            if(parts.length > 1) {
                studentId = parts[1];
            }
        }
        studentId = String(studentId).trim();
        
        // Show processing
        $('#scan-result').removeClass('text-muted text-success text-danger text-warning').addClass('text-info').html('<i class="fas fa-spinner fa-spin"></i> Processing ' + studentId + '...');
        
        // Send AJAX request
        $.ajax({
            url: "{{ route('admin.student-attendance.scan') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                student_id: studentId,
                subject_id: "{{ $selected_subject }}",
                date: "{{ $selected_date }}",
                program: "{{ $selected_program }}",
                session: "{{ $selected_session }}",
                semester: "{{ $selected_semester }}",
                section: "{{ $selected_section }}"
            },
            success: function(response) {
                var currentTime = new Date().toLocaleTimeString();
                
                if(response.status == 'success') {
                    $('#scan-result').removeClass('text-muted text-info text-danger text-warning').addClass('text-success').html('<i class="fas fa-check-circle"></i> ' + response.message);
                    $('#scan-details').html('<strong>' + response.student + '</strong><br>' + response.matricule + ' - ' + response.time);
                    
                    if(response.stats) {
                        $('#stat-present').text(response.stats.present);
                        $('#stat-absent').text(response.stats.absent);
                    }

                    addRecentScan(response.student, response.matricule, response.time, 'success');
                    showAlert('success', response.message, response.student + ' (' + response.matricule + ')', 2000);
                    
                } else if(response.status == 'warning') {
                    playBeep(false);
                    $('#scan-result').removeClass('text-muted text-info text-success text-danger').addClass('text-warning').html('<i class="fas fa-exclamation-triangle"></i> ' + response.message);
                    $('#scan-details').html(studentId);
                    
                    addRecentScan('-', studentId, currentTime, 'warning');
                    showAlert('warning', 'Notice', response.message, 2000);
                    
                } else {
                    playBeep(false);
                    $('#scan-result').removeClass('text-muted text-info text-success text-warning').addClass('text-danger').html('<i class="fas fa-times-circle"></i> ' + response.message);
                    $('#scan-details').html(studentId);
                    
                    addRecentScan('-', studentId, currentTime, 'error');
                    showAlert('error', 'Error', response.message, 2000);
                }
            },
            error: function(xhr) {
                var currentTime = new Date().toLocaleTimeString();
                playBeep(false);
                var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error';
                $('#scan-result').removeClass('text-muted text-info text-success text-warning').addClass('text-danger').html('<i class="fas fa-times-circle"></i> Error: ' + errorMsg);
                $('#scan-details').html(studentId);
                
                addRecentScan('-', studentId, currentTime, 'error');
                showAlert('error', 'Error', errorMsg, 2000);
            },
            complete: function() {
                // Re-enable scanning after 2 seconds - THIS IS KEY
                setTimeout(function() {
                    isScanning = false;
                    $('#scan-result').removeClass('text-success text-info text-danger text-warning').addClass('text-muted').html('<i class="fas fa-qrcode"></i> Ready for next scan...');
                    $('#scan-details').html('');
                    console.log('Scanner ready for next scan');
                }, 2000);
            }
        });
    }

    function onScanFailure(error) {
        // Ignore scan failures - just keep scanning
    }

    // Initialize scanner - same pattern as working staff scanner
    var html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            rememberLastUsedCamera: true,
            aspectRatio: 1.0
        },
        false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    
    console.log('Student Attendance Scanner initialized - ready to scan');
</script>
@endif
@endsection
