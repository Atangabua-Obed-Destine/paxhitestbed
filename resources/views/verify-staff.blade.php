<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 40px;
        }
        .not-found {
            text-align: center;
            padding: 40px 20px;
        }
        .not-found .icon {
            font-size: 80px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .not-found h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .not-found p {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .verified {
            text-align: center;
            margin-bottom: 30px;
        }
        .verified .icon {
            font-size: 80px;
            color: #27ae60;
            margin-bottom: 20px;
        }
        .verified h2 {
            color: #27ae60;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .verified p {
            color: #7f8c8d;
        }
        .staff-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .staff-photo {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 5px solid white;
            overflow: hidden;
            flex-shrink: 0;
            background: white;
        }
        .staff-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-main-info {
            flex: 1;
        }
        .staff-name {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .staff-id {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        .staff-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }
        .info-label {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
        }
        .status-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .status-box h3 {
            color: #155724;
            margin-bottom: 10px;
        }
        .status-box p {
            color: #155724;
            font-size: 14px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 13px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.3s ease;
            margin-top: 15px;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .staff-card {
                flex-direction: column;
                text-align: center;
            }
            .staff-name {
                font-size: 24px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        @media print {
            body {
                background: white;
            }
            .container {
                box-shadow: none;
            }
            .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👤 {{ __('staff_id_verification') }}</h1>
            <p>{{ __('verify_staff_identity_authenticity') }}</p>
        </div>

        <div class="content">
            @if(!$staff_found)
                <div class="not-found">
                    <div class="icon">❌</div>
                    <h2>{{ __('staff_not_found') }}</h2>
                    <p>{{ __('staff_id') }}: <strong>{{ $staff_identifier }}</strong></p>
                    <p>{{ __('staff_verification_failed_message') }}</p>
                </div>
            @else
                <div class="verified">
                    <div class="icon">✅</div>
                    <h2>{{ __('staff_verified') }}</h2>
                    <p>{{ __('this_staff_id_is_authentic') }}</p>
                </div>

                <div class="staff-card">
                    <div class="staff-photo">
                        @if(is_file('uploads/staff/'.$row->photo))
                            <img src="{{ asset('uploads/staff/'.$row->photo) }}" alt="{{ $row->first_name }}">
                        @else
                            <img src="{{ asset('dashboard/images/user.jpg') }}" alt="Staff Photo">
                        @endif
                    </div>
                    <div class="staff-main-info">
                        <div class="staff-name">{{ $row->first_name }} {{ $row->last_name }}</div>
                        <div class="staff-id">{{ __('field_staff_id') }}: {{ $print->prefix ?? '' }}{{ $row->staff_id }}</div>
                        <div class="staff-badges">
                            @if($row->designation)
                            <span class="badge">{{ $row->designation->title }}</span>
                            @endif
                            <span class="badge">
                                @if( $row->gender == 1 )
                                {{ __('gender_male') }}
                                @elseif( $row->gender == 2 )
                                {{ __('gender_female') }}
                                @elseif( $row->gender == 3 )
                                {{ __('gender_other') }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                @if($row->status == '1')
                <div class="status-box">
                    <h3>✓ {{ __('status_active') }}</h3>
                    <p>{{ __('this_staff_member_is_currently_active') }}</p>
                </div>
                @else
                <div class="status-box" style="background: #f8d7da; border-color: #dc3545;">
                    <h3 style="color: #721c24;">✗ {{ __('status_inactive') }}</h3>
                    <p style="color: #721c24;">{{ __('this_staff_member_is_not_active') }}</p>
                </div>
                @endif

                <div class="info-grid">
                    <div class="info-box">
                        <div class="info-label">{{ __('field_email') }}</div>
                        <div class="info-value">{{ $row->email ?? 'N/A' }}</div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">{{ __('field_phone') }}</div>
                        <div class="info-value">{{ $row->phone ?? 'N/A' }}</div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">{{ __('Validity') }}</div>
                        <div class="info-value">{{ $row->id_card_validity }}</div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">{{ __('field_designation') }}</div>
                        <div class="info-value">{{ $row->designation->title ?? 'N/A' }}</div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">{{ __('field_joining_date') }}</div>
                        <div class="info-value">
                            @if(isset($row->joining_date))
                            {{ date('d M Y', strtotime($row->joining_date)) }}
                            @else
                            N/A
                            @endif
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">{{ __('field_gender') }}</div>
                        <div class="info-value">
                            @if( $row->gender == 1 )
                            {{ __('gender_male') }}
                            @elseif( $row->gender == 2 )
                            {{ __('gender_female') }}
                            @elseif( $row->gender == 3 )
                            {{ __('gender_other') }}
                            @else
                            N/A
                            @endif
                        </div>
                    </div>
                </div>

                @if($row->address)
                <div class="info-box" style="margin-top: 20px;">
                    <div class="info-label">{{ __('field_address') }}</div>
                    <div class="info-value">{{ $row->address }}</div>
                </div>
                @endif
            @endif
        </div>

        <div class="footer">
            <p>{{ __('generated_on') }}: {{ date('d M Y, h:i A') }}</p>
            <p>
                <a href="{{ url('/') }}" class="btn">{{ __('back_to_home') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
