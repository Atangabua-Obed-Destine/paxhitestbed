<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style type="text/css" media="screen, print">
        @page { size: A4 portrait; margin: 12mm 10mm; }
        * { font-family: "DejaVu Sans", Arial, sans-serif; box-sizing: border-box; }
        body { margin: 0; color: #1a1a1a; font-size: 12px; }
        .head { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #16294a; padding-bottom: 8px; }
        .head img { height: 64px; width: auto; }
        .head .inst { font-size: 18px; font-weight: bold; color: #16294a; }
        .head .addr { font-size: 10.5px; color: #555; }
        .sheet-title { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 10px 0 4px; }
        .meta { width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 11px; }
        .meta td { padding: 2px 6px; vertical-align: top; }
        .meta .lbl { color: #555; font-weight: bold; white-space: nowrap; }
        .shared-note { font-size: 10.5px; color: #8a5a00; background: #fff8e6; border: 1px solid #f0d98c; padding: 4px 8px; border-radius: 4px; margin-bottom: 8px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #444; padding: 5px 6px; }
        table.grid th { background: #ececec; font-size: 11px; text-align: left; }
        table.grid td { font-size: 11px; height: 30px; }
        .c { text-align: center; }
        .sign-col { width: 95px; }
        .narrow { width: 34px; }
        .pct-col { width: 60px; }
        .elig-col { width: 78px; }
        .prog-col { width: 70px; }
        .foot { margin-top: 18px; font-size: 11px; display: flex; justify-content: space-between; }
        .foot .sig { width: 45%; }
        .foot .line { border-top: 1px solid #444; margin-top: 28px; padding-top: 3px; color: #555; }
        .toolbar { text-align: center; margin: 10px 0; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    @php $logo = optional($setting)->logo_path; @endphp
    <div class="head">
        @if($logo && is_file(public_path('uploads/setting/'.$logo)))
            <img src="{{ asset('uploads/setting/'.$logo) }}" alt="logo">
        @endif
        <div>
            <div class="inst">{{ optional($setting)->title ?? config('app.name') }}</div>
            <div class="addr">
                @if(optional($setting)->address){{ $setting->address }}@endif
                @if(optional($setting)->phone) &middot; {{ $setting->phone }} @endif
                @if(optional($setting)->email) &middot; {{ $setting->email }} @endif
            </div>
        </div>
    </div>

    <div class="sheet-title">{{ __('Examination Attendance — Sign In / Sign Out') }}</div>

    <table class="meta">
        <tr>
            <td class="lbl">{{ __('field_faculty') }}:</td><td>{{ $facultyName ?? '—' }}</td>
            <td class="lbl">{{ __('field_session') }}:</td><td>{{ $sessionName ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">{{ trans_choice('module_program', 1) }}:</td><td>{{ $programName ?? '—' }}</td>
            <td class="lbl">{{ __('field_semester') }}:</td><td>{{ $semesterName ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">{{ __('field_subject') }}:</td>
            <td>{{ optional($subjectModel)->code }} @if(optional($subjectModel)->title)- {{ $subjectModel->title }}@endif</td>
            <td class="lbl">{{ __('field_section') }}:</td><td>{{ $sectionName ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">{{ __('Exam Type') }}:</td><td>{{ optional($examType)->title }}</td>
            <td class="lbl">{{ __('field_date') }}:</td>
            <td>{{ $date ? \Carbon\Carbon::parse($date)->format('l, F j, Y') : '__________________' }}</td>
        </tr>
    </table>

    @if($crossProgram)
        <div class="shared-note">
            <strong>{{ __('All Programmes Mode') }}:</strong>
            {{ __('This course is shared by') }} {{ $sharingPrograms->count() }} {{ __('programmes') }} —
            {{ $sharingPrograms->pluck('shortcode')->filter()->implode(', ') ?: $sharingPrograms->pluck('title')->implode(', ') }}
        </div>
    @endif

    <table class="grid">
        <thead>
            <tr>
                <th class="narrow c">{{ __('field_serial') }}</th>
                <th>{{ __('field_matricule') }}</th>
                <th>{{ __('field_name') }}</th>
                <th class="prog-col">{{ trans_choice('module_program', 1) }}</th>
                <th class="pct-col c">{{ __('Att. Mark') }}</th>
                <th class="elig-col c">{{ __('Eligibility') }}</th>
                <th class="sign-col c">{{ __('Sign In') }}</th>
                <th class="sign-col c">{{ __('Sign Out') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sheet as $i => $r)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $r['matricule'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['program'] }}</td>
                    <td class="c">{{ is_null($r['percentage']) ? 'N/A' : rtrim(rtrim(number_format($r['attendance_mark'],2),'0'),'.').' / '.rtrim(rtrim(number_format($r['attendance_contribution'],2),'0'),'.') }}</td>
                    <td class="c">
                        @if($r['bypassed']) {{ __('Bypassed') }}
                        @elseif($r['eligible']) {{ __('Eligible') }}
                        @else {{ __('Not Eligible') }}
                        @endif
                    </td>
                    <td class="sign-col"></td>
                    <td class="sign-col"></td>
                </tr>
            @empty
                <tr><td colspan="8" class="c">{{ __('No students found for this selection.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        <div class="sig"><div class="line">{{ __('Invigilator name & signature') }}</div></div>
        <div class="sig"><div class="line">{{ __('Date') }}</div></div>
    </div>

    <div class="toolbar">
        <button onclick="window.print()">{{ __('btn_print') ?? 'Print' }}</button>
    </div>

    <script>window.onload = function(){ window.print(); };</script>
</body>
</html>
