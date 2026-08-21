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
    <title>Staff ID Cards</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;400;500;600&display=swap');
        @page { size: auto; margin: 10px; }
        .page-break { page-break-after: always; }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Anton', sans-serif !important;
        }

        .id-card {
            width: 639px;
            height: 1011px;
            position: relative;
            background: url('{{ $cardBackground }}') no-repeat center center;
            background-size: cover;
            color: #000;
            margin: 0 auto;
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

        /* Validity Period - At bottom */
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
    </style>
</head>
<body>

@if(isset($rows))
    @foreach($rows as $row)
        <div class="id-card">
            <!-- Staff Photo -->
            <div class="photo">
                @if($row->photo && file_exists(public_path('uploads/user/'.$row->photo)))
                    <img src="{{ avatar_url($row->photo) }}" alt="Photo">
                @else
                    <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Photo">
                @endif
            </div>

            <!-- QR Code for Verification -->
            <div class="qr-code">
                @php
                    $verificationUrl = url('verify-staff/'.$row->id);
                    $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationUrl);
                @endphp
                <img src="{{ $qrCode }}" alt="QR Code">
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

        <div class="page-break"></div>
    @endforeach
@else
    <div class="id-card">
        <!-- Staff Photo -->
        <div class="photo">
            @if($row->photo && file_exists(public_path('uploads/user/'.$row->photo)))
                <img src="{{ avatar_url($row->photo) }}" alt="Photo">
            @else
                <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Photo">
            @endif
        </div>

        <!-- QR Code for Verification -->
        <div class="qr-code">
            @php
                $verificationUrl = url('verify-staff/'.$row->id);
                $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationUrl);
            @endphp
            <img src="{{ $qrCode }}" alt="QR Code">
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
@endif

</body>
</html>
