<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .verification-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .success-icon {
            color: #28a745;
        }
        .error-icon {
            color: #dc3545;
        }
        .student-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 20px auto;
            display: block;
            border: 5px solid #667eea;
        }
        .info-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="verification-card text-center">
        @if($found)
            <div class="verification-icon success-icon">✓</div>
            <h2 class="text-success mb-3">{{ $message }}</h2>
            
            <div class="text-start mt-4">
                @if($student->photo && file_exists(public_path('uploads/student/'.$student->photo)))
                    <img src="{{ asset('uploads/student/'.$student->photo) }}" alt="Photo" class="student-photo">
                @else
                    <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Photo" class="student-photo">
                @endif

                <div class="info-row">
                    <span class="info-label">Student ID:</span>
                    <span class="info-value float-end">{{ $student->student_id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value float-end">{{ $student->first_name }} {{ $student->last_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value float-end">{{ $student->dob ? \Carbon\Carbon::parse($student->dob)->format('d M Y') : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Program:</span>
                    <span class="info-value float-end">{{ $student->program->title ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Batch:</span>
                    <span class="info-value float-end">{{ $student->batch->title ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value float-end">
                        @if($student->status == 1)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value float-end">{{ $student->email }}</span>
                </div>
            </div>
        @else
            <div class="verification-icon error-icon">✗</div>
            <h2 class="text-danger mb-3">Verification Failed</h2>
            <p class="text-muted">{{ $message }}</p>
            <p class="mt-3"><strong>Student ID:</strong> {{ $student_id }}</p>
        @endif

        <div class="mt-4">
            <small class="text-muted">Verified at: {{ now()->format('d M Y, H:i:s') }}</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
