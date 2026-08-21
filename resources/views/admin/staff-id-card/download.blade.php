<!DOCTYPE html>
{{-- The card artwork is uploaded under the ID Card Setting screen. Until one
     is uploaded we fall back to the file that used to be hardcoded here, so an
     install that has not configured anything looks exactly as it did before. --}}
@php
    $cardBackground = !empty($print->background)
        ? asset('uploads/card-setting/' . $print->background)
        : asset('uploads/templates/staffid.jpg');
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Download Staff ID Card</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;400;500;600&display=swap');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        
        .download-container {
            max-width: 900px;
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
            color: white;
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
        
        .btn-back:hover {
            background: #545b62;
            color: white;
        }
        
        .id-card-wrapper {
            display: inline-block;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .id-card {
            width: 639px;
            height: 1011px;
            position: relative;
            background: url('{{ $cardBackground }}') no-repeat center center;
            background-size: cover;
            color: #000;
        }

        /* Staff Photo - Positioned in the rounded placeholder */
        .id-card .photo {
            position: absolute;
            top: 325px;
            left: 50%;
            transform: translateX(-50%);
            width: 330px;
            height: 330px;
            border-radius: 70px;
            overflow: hidden;
            background: #fff;
        }
        .id-card .photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* QR Code - Bottom-right corner of profile photo */
        .id-card .qr-code {
            position: absolute;
            /* Photo: top 325, left ~155, width 330 → right edge 485, bottom 655.
               QR 110x110 centered on photo's bottom-right corner. */
            top: 555px;
            left: 430px;
            width: 110px;
            height: 110px;
            background: white;
            padding: 5px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }
        .id-card .qr-code img {
            width: 100%;
            height: 100%;
        }

        /* First Name - Large text on top */
        .id-card .first-name {
            position: absolute;
            top: 685px;
            left: 0;
            right: 0;
            text-align: center;
            font-family: 'Anton', sans-serif !important;
            font-size: 50px;
            font-weight: 400;
            letter-spacing: 4px;
            color: #000;
            text-transform: uppercase;
        }

        /* Last Name - Below first name */
        .id-card .last-name {
            position: absolute;
            top: 740px;
            left: 0;
            right: 0;
            text-align: center;
            font-family: 'Oswald', sans-serif !important;
            font-size: 44px;
            font-weight: 500;
            letter-spacing: 3px;
            color: #000;
            text-transform: uppercase;
        }

        /* Designation - Below first name */
        .id-card .designation {
            position: absolute;
            top: 815px;
            left: 0;
            right: 0;
            text-align: center;
            font-family: 'Oswald', sans-serif !important;
            font-size: 28px;
            font-weight: 500;
            letter-spacing: 2px;
            color: #000;
            text-transform: uppercase;
        }

        /* Staff ID - aligned with side dotted decoration */
        .id-card .staff-id {
            position: absolute;
            top: 885px;
            left: 0;
            right: 0;
            text-align: center;
            font-family: 'Oswald', sans-serif !important;
            font-size: 44px;
            font-weight: 500;
            letter-spacing: 2px;
            color: #000;
        }

        /* Department - At bottom */
        .id-card .validity {
            position: absolute;
            top: 938px;
            left: 0;
            right: 0;
            text-align: center;
            font-family: 'Oswald', sans-serif !important;
            font-size: 28px;
            font-weight: 300;
            color: #000;
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
        <h3>📇 Download Staff ID Card</h3>
        <button class="btn-download" onclick="downloadAsImage()">
            ⬇️ Download as PNG
        </button>
        <button class="btn-download pdf" onclick="downloadAsPDF()">
            📄 Download as PDF
        </button>
        <a href="{{ route('admin.staff-id-card.index') }}" class="btn-back">
            ← Back to List
        </a>
    </div>

    <div class="id-card-wrapper">
        <div class="id-card" id="id-card-{{ $row->id }}" data-staff-id="{{ $row->staff_id }}">
            <!-- Staff Photo -->
            <div class="photo">
                @if($row->photo && file_exists(public_path('uploads/user/'.$row->photo)))
                    <img src="{{ avatar_url($row->photo) }}" alt="Photo" crossorigin="anonymous">
                @else
                    <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Photo" crossorigin="anonymous">
                @endif
            </div>

            <!-- QR Code for Verification -->
            <div class="qr-code">
                @php
                    $verificationUrl = url('verify-staff/'.$row->id);
                    $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationUrl);
                @endphp
                <img src="{{ $qrCode }}" alt="QR Code" crossorigin="anonymous">
            </div>

            <!-- First Name on top (large) -->
            <div class="first-name">{{ $row->first_name }}</div>

            <!-- Last Name below (smaller) -->
            <div class="last-name">{{ $row->last_name }}</div>

            <!-- Designation -->
            <div class="designation">{{ $row->designation->title ?? 'N/A' }}</div>

            <!-- Staff ID -->
            <div class="staff-id">ID:{{ $row->staff_id }}</div>

            <!-- Validity Period -->
            <div class="validity">VALIDITY: {{ $row->id_card_validity }}</div>
        </div>
    </div>
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
        const staffId = card.getAttribute('data-staff-id');
        
        html2canvas(card, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: null
        }).then(function(canvas) {
            const link = document.createElement('a');
            link.download = 'Staff_ID_Card_' + staffId + '.png';
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
        const staffId = card.getAttribute('data-staff-id');
        
        html2canvas(card, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: null
        }).then(function(canvas) {
            const { jsPDF } = window.jspdf;
            
            // Staff ID card dimensions - portrait orientation (matching 639x1011 ratio)
            const cardWidth = 63.9;  // mm
            const cardHeight = 101.1; // mm
            
            // Create PDF with custom size
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: [cardWidth + 10, cardHeight + 10]
            });
            
            const imgData = canvas.toDataURL('image/png');
            pdf.addImage(imgData, 'PNG', 5, 5, cardWidth, cardHeight);
            pdf.save('Staff_ID_Card_' + staffId + '.pdf');
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
