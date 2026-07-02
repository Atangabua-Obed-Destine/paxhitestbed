<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $data['subject'] ?? 'Documents Required' }}</title>
  </head>
  <body style="background-color:#f6f6f6; font-family: sans-serif; margin:0; padding:0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;">
      <tr>
        <td>&nbsp;</td>
        <td style="max-width:640px; margin:0 auto; padding:20px; display:block;">
          <div style="background:#ffffff; border-radius:6px; padding:24px; border:1px solid #e5e5e5;">
            <h2 style="margin-top:0; color:#333;">{{ __('Documents Need Your Attention') }}</h2>

            <p>{{ __('Hi') }} {{ $data['first_name'] ?? '' }},</p>

            <p>{{ __('Our admissions team has reviewed your application') }}
              <strong>#{{ $data['registration_no'] ?? '' }}</strong>
              {{ __('and requires you to resubmit the following document(s):') }}</p>

            <ul style="line-height:1.7;">
              @foreach(($data['documents'] ?? []) as $doc)
                <li>
                  <strong>{{ $doc['label'] ?? '' }}</strong>
                  @if(!empty($doc['reason']))
                    &mdash; <em style="color:#a94442;">{{ $doc['reason'] }}</em>
                  @endif
                </li>
              @endforeach
            </ul>

            <p style="margin-top:24px;">
              {{ __('Please sign in to your applicant portal to upload the corrected files.') }}
            </p>

            @if(!empty($data['portal_url']))
              <p style="text-align:center; margin:24px 0;">
                <a href="{{ $data['portal_url'] }}" style="background:#0d6efd; color:#ffffff; padding:12px 24px; border-radius:4px; text-decoration:none; display:inline-block;">
                  {{ __('Open Applicant Portal') }}
                </a>
              </p>
            @endif

            <p style="color:#777; font-size:13px; margin-top:32px;">
              {{ __('If you did not expect this email, please contact the admissions office.') }}
            </p>

            <p style="color:#777; font-size:13px;">
              &mdash; {{ $data['sender'] ?? config('app.name') }}
            </p>
          </div>
        </td>
        <td>&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
