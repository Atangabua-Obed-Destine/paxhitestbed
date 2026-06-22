<!doctype html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $data['subject'] ?? __('Offer of Admission') }}</title>
</head>
<body style="background-color: #f6f6f6; font-family: sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.5; margin: 0; padding: 0;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f6f6f6;">
        <tr>
            <td style="padding: 24px 12px;" align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="580" style="max-width:580px; width:100%; background:#ffffff; border-radius:6px; overflow:hidden;">
                    <tr>
                        <td style="background:#16294a; color:#ffffff; padding:20px 24px; font-size:18px; font-weight:bold;">
                            {{ $data['institution'] ?? config('app.name') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px;">
                            <h2 style="font-size:20px; color:#16294a; margin:0 0 16px;">{{ __('Congratulations!') }}</h2>
                            <p style="margin:0 0 14px;">{{ __('Dear') }} {{ $data['name'] ?? '' }},</p>
                            <p style="margin:0 0 14px;">
                                {{ __('We are pleased to inform you that your application for admission to') }}
                                <strong>{{ $data['institution'] ?? config('app.name') }}</strong>
                                {{ __('has been successful.') }}
                            </p>
                            <p style="margin:0 0 14px;">
                                {{ __('Your official letter of admission is attached to this email as a PDF document. Please read it carefully for the next steps.') }}
                            </p>
                            <p style="margin:18px 0 0; color:#6b7686;">{{ __('Warm regards,') }}<br>{{ __('Admissions Office') }}<br>{{ $data['institution'] ?? config('app.name') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f0f2f6; color:#8a93a2; padding:14px 24px; font-size:12px;">
                            {{ __('This is an automated message. Please keep the attached letter for your records.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
