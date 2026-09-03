<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} &middot; {{ institution_name() }}</title>
    <style>
        :root {
            --ink: #1a1a1a;
            --muted: #5c6660;
            --rule: #dfe3e0;
            --paper: #f4f6f4;
            --ok: #1f6b4d;
            --ok-soft: #e7f2ec;
            --bad: #9c3a24;
            --bad-soft: #f8ece8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 20px 64px;
            background: var(--paper);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.6;
        }
        .wrap { max-width: 640px; margin: 0 auto; }
        .institution {
            text-align: center;
            font-size: 12px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 20px;
        }
        .card {
            background: #fff;
            border: 1px solid var(--rule);
            border-radius: 8px;
            overflow: hidden;
        }
        .banner {
            padding: 20px 24px;
            border-bottom: 1px solid var(--rule);
        }
        .banner.ok { background: var(--ok-soft); }
        .banner.bad { background: var(--bad-soft); }
        .banner h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.2px;
        }
        .banner.ok h1 { color: var(--ok); }
        .banner.bad h1 { color: var(--bad); }
        .banner p { margin: 6px 0 0; font-size: 14px; color: var(--muted); }

        .body { padding: 8px 24px 24px; }
        dl { margin: 0; }
        .row {
            display: flex;
            gap: 16px;
            padding: 11px 0;
            border-bottom: 1px solid var(--rule);
            font-size: 14px;
        }
        .row:last-child { border-bottom: 0; }
        dt {
            flex: 0 0 42%;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin: 0;
            padding-top: 2px;
        }
        dd { margin: 0; font-weight: 600; }
        .mono {
            font-family: ui-monospace, Consolas, "Courier New", monospace;
            letter-spacing: 0.5px;
        }
        .note {
            margin-top: 18px;
            font-size: 12.5px;
            color: var(--muted);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 13px;
        }
        th, td { text-align: left; padding: 7px 8px 7px 0; border-bottom: 1px solid var(--rule); }
        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
        }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        h2 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--muted);
            margin: 26px 0 0;
        }
        .scroll { overflow-x: auto; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="institution">{{ institution_name() }}</div>

    <div class="card">
        @if($found)
            <div class="banner ok">
                <h1>{{ __('Transcript verified') }}</h1>
                <p>{{ __('This transcript was issued by this institution. The figures below are what it showed when it was issued.') }}</p>
            </div>

            <div class="body">
                <dl>
                    <div class="row"><dt>{{ __('Name') }}</dt><dd>{{ $record->student_name }}</dd></div>
                    <div class="row"><dt>{{ __('Matricule') }}</dt><dd class="mono">{{ $record->matricule }}</dd></div>
                    <div class="row"><dt>{{ __('Programme') }}</dt><dd>{{ $record->programme_name ?? '—' }}</dd></div>
                    <div class="row"><dt>{{ __('Cumulative GPA') }}</dt><dd>{{ number_format((float) $record->cumulative_gpa, 2) }}</dd></div>
                    <div class="row"><dt>{{ __('Credits earned') }}</dt><dd>{{ number_format((float) $record->credits_earned, 1) }} {{ __('of') }} {{ number_format((float) $record->total_credits, 1) }}</dd></div>
                    <div class="row"><dt>{{ __('Courses') }}</dt><dd>{{ $record->total_courses }}</dd></div>
                    <div class="row"><dt>{{ __('Standing') }}</dt><dd>{{ $record->standing }}</dd></div>
                    <div class="row"><dt>{{ __('Issued') }}</dt><dd>{{ $record->issued_at?->format('d F Y') }}</dd></div>
                    <div class="row"><dt>{{ __('Reference') }}</dt><dd class="mono">{{ $record->verification_code }}</dd></div>
                </dl>

                @if(!empty($record->courses_snapshot))
                <h2>{{ __('Courses as issued') }}</h2>
                <div class="scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th class="num">{{ __('Credit') }}</th>
                                <th class="num">{{ __('Grade') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($record->courses_snapshot as $course)
                            <tr>
                                <td class="mono">{{ $course['code'] ?? '—' }}</td>
                                <td>{{ $course['title'] ?? '—' }}</td>
                                <td class="num">{{ number_format((float) ($course['credit'] ?? 0), 1) }}</td>
                                <td class="num">{{ $course['grade'] ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <p class="note">
                    {{ __('If the printed document differs from anything above, it has been altered since it was issued.') }}
                </p>
            </div>
        @else
            <div class="banner bad">
                <h1>{{ __('Not verified') }}</h1>
                <p>{{ __('No transcript was issued with this reference.') }}</p>
            </div>

            <div class="body">
                <dl>
                    <div class="row"><dt>{{ __('Reference checked') }}</dt><dd class="mono">{{ $code }}</dd></div>
                </dl>
                <p class="note">
                    {{ __('Check the reference was typed correctly. If it was scanned from a document, that document did not come from this institution.') }}
                </p>
            </div>
        @endif
    </div>

</div>
</body>
</html>
