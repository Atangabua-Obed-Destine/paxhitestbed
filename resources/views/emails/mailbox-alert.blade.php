<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>{{ __('New message') }}</title>
</head>
<body style="margin:0;padding:0;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f7;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:8px;border:1px solid #e1e5ea;">
<tr><td style="background:#1b2a41;color:#ffffff;padding:16px 24px;border-radius:8px 8px 0 0;font-size:15px;font-weight:bold;">{{ $fromName }}</td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.55;">
<p style="margin:0 0 12px;">{{ $recipientName ? __('Hello :name,', ['name' => $recipientName]) : __('Hello,') }}</p>
<p style="margin:0 0 16px;">{{ __('You have a new message in your PAXHI mailbox.') }}</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#f7f9fb;border-left:3px solid #2f80ed;margin:0 0 20px;">
<tr><td style="padding:12px 14px;font-size:14px;">
<div style="color:#52606d;">{{ __('From') }}</div><div style="font-weight:bold;margin-bottom:8px;">{{ $sender }}</div>
<div style="color:#52606d;">{{ __('Subject') }}</div><div style="font-weight:bold;">{{ $topic }}</div>
</td></tr></table>
<p style="margin:0 0 24px;"><a href="{{ $link }}" style="display:inline-block;background:#2f80ed;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:6px;font-weight:bold;">{{ __('Open the message') }}</a></p>
<p style="margin:0;font-size:13px;color:#7b8794;">{{ __('For your privacy the message itself is shown only after you sign in. Please reply in your mailbox, not to this email.') }}</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
