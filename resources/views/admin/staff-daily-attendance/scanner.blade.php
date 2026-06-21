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
                    </div>
                    <div class="card-block text-center">
                        
                        <div class="row mb-4">
                            <div class="col-md-12 text-center">
                                <h4 class="text-primary">{{ $today_date }}</h4>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h3>{{ $total_staff }}</h3>
                                        <p class="mb-0">Total Staff</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-present">{{ $present_count }}</h3>
                                        <p class="mb-0">Present Today</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-active">{{ $clocked_in_now }}</h3>
                                        <p class="mb-0">Currently Active</p>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                <i class="fas fa-info-circle"></i> Please position your ID card QR code in front of the camera.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script src="{{ asset('dashboard/js/html5-qrcode.min.js') }}"></script>
<script type="text/javascript">
    "use strict";
    
    // Debounce mechanism to prevent rapid-fire scanning
    let isScanning = false;

    function playBeep() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const playTone = (freq, start, duration) => {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
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
            const now = audioCtx.currentTime;
            playTone(523.25, now, 0.15); 
            playTone(659.25, now + 0.1, 0.3); 
        } catch (e) {
            console.error("Audio play failed", e);
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        if (isScanning) return;
        isScanning = true;

        // Handle on success condition with the decoded text or result.
        console.log(`Scan result: ${decodedText}`, decodedResult);

        // Play Sound
        playBeep();

        // Extract Staff ID from URL if it's a URL (e.g. /verify-staff/STAFF001)
        let staffId = decodedText;
        if(typeof decodedText === 'string' && decodedText.includes('/verify-staff/')) {
            const parts = decodedText.split('/verify-staff/');
            if(parts.length > 1) {
                staffId = parts[1];
            }
        }
        
        // Send AJAX request
        $.ajax({
            url: "{{ route('admin.staff-daily-attendance.scan') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                staff_id: staffId
            },
            success: function(response) {
                if(response.status == 'success') {
                    $('#scan-result').removeClass('text-muted text-danger text-warning').addClass('text-success').html(response.message);
                    $('#scan-details').html('<strong>' + response.user + '</strong><br>' + response.time + (response.duration ? ' (' + response.duration + ')' : ''));
                    
                    // Update Stats
                    if(response.stats) {
                        $('#stat-present').text(response.stats.present);
                        $('#stat-active').text(response.stats.active);
                    }

                    // Visual feedback
                    Swal.fire({
                        icon: 'success',
                        title: response.message,
                        text: response.user + ' - ' + response.time,
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else if(response.status == 'warning') {
                     $('#scan-result').removeClass('text-muted text-success text-danger').addClass('text-warning').html(response.message);
                     
                     Swal.fire({
                        icon: 'warning',
                        title: 'Notice',
                        text: response.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    $('#scan-result').removeClass('text-muted text-success text-warning').addClass('text-danger').html(response.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr) {
                $('#scan-result').removeClass('text-muted text-success text-warning').addClass('text-danger').html('Error: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error'));
            },
            complete: function() {
                // Re-enable scanning after 3 seconds
                setTimeout(function() {
                    isScanning = false;
                    $('#scan-result').removeClass('text-success text-danger text-warning').addClass('text-muted').html('Waiting for scan...');
                    $('#scan-details').html('');
                }, 3000);
            }
        });
    }

    function onScanFailure(error) {
        // handle scan failure, usually better to ignore and keep scanning.
        // console.warn(`Code scan error = ${error}`);
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            rememberLastUsedCamera: true,
            aspectRatio: 1.0
        },
        /* verbose= */ false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>
@endsection
