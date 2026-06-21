<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .code-box {
            background: #fff;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🔐 Two-Factor Authentication</h2>
        </div>

        <p>Hello <strong>{{ $data['userName'] }}</strong>,</p>

        <p>A login attempt was made to your account. Please use the verification code below to complete your login:</p>

        <div class="code-box">
            <div class="code">{{ $data['code'] }}</div>
            <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">Enter this code to verify your identity</p>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong> This code will expire in <strong>{{ $data['expiryMinutes'] }} minutes</strong>.
        </div>

        <p><strong>Security Tips:</strong></p>
        <ul>
            <li>Never share this code with anyone</li>
            <li>We will never ask for your code via phone or email</li>
            <li>If you didn't request this code, please contact support immediately</li>
        </ul>

        <div class="footer">
            <p>This is an automated message from {{ config('app.name') }}. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
