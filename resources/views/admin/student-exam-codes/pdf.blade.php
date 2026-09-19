<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Pre Registration Code List') }} — {{ $session->title ?? '' }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; margin: 0; }
        .commission { text-align: center; font-weight: bold; text-transform: uppercase; line-height: 1.35; }
        .commission .en { font-weight: normal; }
        .list-title { text-align: center; font-weight: bold; text-transform: uppercase; margin-top: 10px; }
        .school { text-align: center; font-weight: bold; text-transform: uppercase; margin: 6px 0 14px; }
        .option { width: 100%; margin: 14px 0 4px; border-collapse: collapse; }
        .option td { border: none; padding: 0; font-weight: bold; font-size: 11pt; }
        .option .level { text-align: right; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        th, td { border: 1px solid #000; padding: 3px 6px; font-size: 10.5pt; }
        th { text-align: left; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .col-no { width: 6%; }
        .col-code { width: 26%; }
        .empty { text-align: center; font-style: italic; margin-top: 30px; }
    </style>
</head>
<body>

    <div class="commission">
        <div>Commission Nationale d'Organisation des Examens Nationaux et des Concours (CNOENC)</div>
        <div class="en">National Committee for the Organisation of National Exams and Competitive Entrance Examinations (NCONECE)</div>
    </div>

    <div class="list-title">Liste des Codes d'Inscription / Pre Registration Code List</div>
    <div class="school">{{ institution_name() }}</div>

    @forelse ($groups as $programme => $students)
        <table class="option">
            <tr>
                <td>Option : {{ $programme }}</td>
                <td class="level">Niveau: {{ $level }}</td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-code">Code</th>
                    <th>Nom et Prénom</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $index => $enroll)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $codes[$enroll->student_id] ?? '' }}</td>
                        <td>{{ strtoupper(trim(($enroll->student->first_name ?? '') . ' ' . ($enroll->student->last_name ?? ''))) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="empty">{{ __('No codes have been recorded for this year and level yet.') }}</p>
    @endforelse

</body>
</html>
