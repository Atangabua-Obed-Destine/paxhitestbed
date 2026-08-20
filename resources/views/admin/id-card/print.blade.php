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
    <title>ID Cards</title>

    <link rel="stylesheet" type="text/css" href="{{ asset('dashboard/css/prints/student_id_card.css') }}" media="screen, print">

    <style>
        @page { size: auto; margin: 10px; }
        .page-break { page-break-after: always; }
        @import url('https://fonts.googleapis.com/css2?family=Anton&display=swap');
        /* @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&display=swap'); */

/* Force background images to print */
@media print {
    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    .id-card {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
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
.id-card div{
    margin-top: 25px;
}

/* Student Photo */
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

/* Text Fields */
.id-card .name {
    position: absolute;
    top: 165px;
    left: 150px;
    font-size: 22px;
    /* font-family: "Anton"; */
    font-weight: 700;
    letter-spacing: 0.5px;
    /* font-style: normal; */
    color: #001F5B; /* dark blue tone to match template */
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

/* QR Code */
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

/* Validity Period */
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

    </style>
</head>
<body>

@foreach($rows as $enrollment)
    @php
        // $enrollment is now a StudentEnroll, get the student
        $student = $enrollment->student;
        
        // Skip if student not found
        if(!$student) {
            continue;
        }
        
        $displayMatricule = $enrollment->matricule ?? $student->student_id;
        
        // Calculate validity period based on program's validity_years
        $validityYears = $enrollment->program->validity_years ?? 4;
        $currentYear = date('Y');
        $expiryYear = $currentYear + $validityYears;
        $validityPeriod = $currentYear . '-' . $expiryYear;
    @endphp
    <div class="id-card">
        <!-- Validity Period -->
        <div class="validity">VALIDITY: {{ $validityPeriod }}</div>

        <!-- Student Photo -->
        <div class="photo">
            @if($student->photo && file_exists(public_path('uploads/student/'.$student->photo)))
                <img src="{{ asset('uploads/student/'.$student->photo) }}" alt="Photo">
            @else
                <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Photo">
            @endif
        </div>

        <!-- Student Name -->
        <div class="name">{{ $student->first_name }} {{ $student->last_name }}</div>

        <!-- Date of Birth -->
        <div class="dob">{{ date('d/m/Y', strtotime($student->dob)) }}</div>

        <!-- Sex -->
        <div class="sex">
            @if($student->gender == 1)
                {{ __('gender_male') }}
            @elseif($student->gender == 2)
                {{ __('gender_female') }}
            @else
                {{ __('gender_other') }}
            @endif
        </div>

        <!-- Matricule -->
        <div class="matricule">{{ $print->prefix ?? '' }}{{ $displayMatricule }}</div>

        <!-- Faculty -->
        <div  class="faculty">{{ $enrollment->program->faculty->shortcode ?? $enrollment->program->faculty->title ?? '' }}</div>

        <!-- Date Issued -->
        <div class="date-issued">{{ date('d/m/Y') }}</div>

        <!-- QR Code for Verification -->
        <div class="qr-code">
            @php
                $verificationUrl = route('student.verify', $displayMatricule);
                $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationUrl);
            @endphp
            <img src="{{ $qrCode }}" alt="QR Code">
        </div>
    </div>

    <div class="page-break"></div>
@endforeach

</body>
</html>
