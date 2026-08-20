<!DOCTYPE html>
{{-- The card artwork is uploaded under the ID Card Setting screen. Until one
     is uploaded we fall back to the file that used to be hardcoded here, so an
     install that has not configured anything looks exactly as it did before. --}}
@php
    $cardBackground = !empty($print->background)
        ? asset('uploads/card-setting/' . $print->background)
        : asset('uploads/templates/paxid.jpg');
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Download ID Card</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('dashboard/css/prints/student_id_card.css') }}" media="screen">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Anton&display=swap');
        
        body {
            background: #f5f5f5;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        
        .download-container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        
        .download-header {
            margin-bottom: 20px;
            padding: 15px;
            background: #667eea;
            color: white;
            border-radius: 8px;
        }
        
        .download-header h3 {
            margin: 0 0 10px 0;
        }
        
        .btn-download {
            display: inline-block;
            padding: 12px 30px;
            margin: 5px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-download:hover {
            background: #218838;
        }
        
        .btn-download.pdf {
            background: #dc3545;
        }
        
        .btn-download.pdf:hover {
            background: #c82333;
        }
        
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .id-card-wrapper {
            display: inline-block;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .id-card {
            width: 711px;
            height: 450px;
            position: relative;
            background: url('{{ $cardBackground }}') no-repeat center center;
            background-size: cover;
            font-family: 'Anton'; 
            font-style: normal;
            color: #000;
        }
        
        .id-card div {
            margin-top: 25px;
        }
        
        .id-card .photo {
            position: absolute;
            bottom: 35px;
            right: 15px;
            width: 182px;
            height: 199px;
            border: 2px solid #001F5B;
        }
        
        .id-card .photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .id-card .name {
            position: absolute;
            top: 165px;
            left: 150px;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #001F5B;
        }
        
        .id-card .dob,
        .id-card .sex,
        .id-card .matricule,
        .id-card .faculty,
        .id-card .date-issued {
            position: absolute;
            left: 220px;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #000;
            font-family: "Anton";
        }
        
        .id-card .dob { top: 188px; left: 150px; }
        .id-card .sex { top: 207px; left: 150px; }
        .id-card .matricule { top: 228px; left: 150px; font-weight: 700; }
        .id-card .faculty { top: 251px; left: 150px; }
        .id-card .date-issued { top: 272px; left: 150px; }
        
        .id-card .qr-code {
            position: absolute;
            bottom: 35px;
            right: 210px;
            width: 110px;
            height: 110px;
            background: white;
            padding: 5px;
            border: 2px solid #001F5B;
            border-radius: 5px;
        }
        
        .id-card .qr-code img {
            width: 100%;
            height: 100%;
        }
        
        .id-card .validity {
            position: absolute;
            top: -5px;
            right: 15px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            padding: 5px 10px;
            font-family: 'Anton';
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <div>Generating image...</div>
</div>

<div class="download-container">
    <div class="download-header">
        <h3><i class="fas fa-id-card"></i> Download ID Card</h3>
        <button class="btn-download" onclick="downloadAsImage()">
            <i class="fas fa-download"></i> Download as PNG
        </button>
        <button class="btn-download pdf" onclick="downloadAsPDF()">
            <i class="fas fa-file-pdf"></i> Download as PDF
        </button>
        <a href="{{ route('admin.id-card.index') }}" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    @foreach($rows as $enrollment)
        @php
            $student = $enrollment->student;
            if(!$student) continue;
            
            $displayMatricule = $enrollment->matricule ?? $student->student_id;
            $validityYears = $enrollment->program->validity_years ?? 4;
            $currentYear = date('Y');
            $expiryYear = $currentYear + $validityYears;
            $validityPeriod = $currentYear . '-' . $expiryYear;
        @endphp
        
        <div class="id-card-wrapper">
            <div class="id-card" id="id-card-{{ $enrollment->id }}" data-matricule="{{ $displayMatricule }}">
                <div class="validity">VALIDITY: {{ $validityPeriod }}</div>
                
                <div class="photo">
                    @if($student->photo && file_exists(public_path('uploads/student/'.$student->photo)))
                        <img src="{{ asset('uploads/student/'.$student->photo) }}" alt="Photo" crossorigin="anonymous">
                    @else
                        <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Photo" crossorigin="anonymous">
                    @endif
                </div>
                
                <div class="name">{{ $student->first_name }} {{ $student->last_name }}</div>
                <div class="dob">{{ date('d/m/Y', strtotime($student->dob)) }}</div>
                <div class="sex">
                    @if($student->gender == 1)
                        {{ __('gender_male') }}
                    @elseif($student->gender == 2)
                        {{ __('gender_female') }}
                    @else
                        {{ __('gender_other') }}
                    @endif
                </div>
                <div class="matricule">{{ $print->prefix ?? '' }}{{ $displayMatricule }}</div>
                <div class="faculty">{{ $enrollment->program->faculty->shortcode ?? $enrollment->program->faculty->title ?? '' }}</div>
                <div class="date-issued">{{ date('d/m/Y') }}</div>
                
                <div class="qr-code">
                    @php
                        $verificationUrl = route('student.verify', $displayMatricule);
                        $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationUrl);
                    @endphp
                    <img src="{{ $qrCode }}" alt="QR Code" crossorigin="anonymous">
                </div>
            </div>
        </div>
    @endforeach
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }
    
    function downloadAsImage() {
        showLoading();
        
        const card = document.querySelector('.id-card');
        const matricule = card.getAttribute('data-matricule');
        
        html2canvas(card, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: null
        }).then(function(canvas) {
            const link = document.createElement('a');
            link.download = 'ID_Card_' + matricule + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            hideLoading();
        }).catch(function(error) {
            console.error('Error generating image:', error);
            hideLoading();
            alert('Error generating image. Please try again.');
        });
    }
    
    function downloadAsPDF() {
        showLoading();
        
        const card = document.querySelector('.id-card');
        const matricule = card.getAttribute('data-matricule');
        
        html2canvas(card, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: null
        }).then(function(canvas) {
            const { jsPDF } = window.jspdf;
            
            // ID card dimensions in mm (credit card size approximately)
            const cardWidth = 85.6;
            const cardHeight = 54;
            
            // Create PDF with custom size
            const pdf = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: [cardHeight + 10, cardWidth + 10]
            });
            
            const imgData = canvas.toDataURL('image/png');
            pdf.addImage(imgData, 'PNG', 5, 5, cardWidth, cardHeight);
            pdf.save('ID_Card_' + matricule + '.pdf');
            hideLoading();
        }).catch(function(error) {
            console.error('Error generating PDF:', error);
            hideLoading();
            alert('Error generating PDF. Please try again.');
        });
    }
</script>

</body>
</html>
