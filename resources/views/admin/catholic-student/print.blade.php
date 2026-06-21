<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .badge-yes {
            color: #28a745;
            font-weight: bold;
        }
        .badge-no {
            color: #6c757d;
        }
        @media print {
            button {
                display: none;
            }
        }
        .print-btn {
            margin: 20px 0;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print This Report</button>

    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Generated on: {{ $print_date }}</p>
        <p>Total Students: {{ $students->count() }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Program</th>
                <th>Session</th>
                <th>Baptised</th>
                <th>Confirmed</th>
                <th>First Communion</th>
            </tr>
        </thead>
        <tbody>
            @forelse($students as $index => $student)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $student->student ? $student->student->student_id : 'N/A' }}</td>
                <td>{{ $student->student ? $student->student->first_name . ' ' . $student->student->last_name : 'N/A' }}</td>
                <td>{{ $student->program ? $student->program->title : 'N/A' }}</td>
                <td>{{ $student->session ? $student->session->title : 'N/A' }}</td>
                <td class="{{ $student->is_catholic_baptised ? 'badge-yes' : 'badge-no' }}">
                    {{ $student->is_catholic_baptised ? 'Yes' : 'No' }}
                </td>
                <td class="{{ $student->is_confirmed ? 'badge-yes' : 'badge-no' }}">
                    {{ $student->is_confirmed ? 'Yes' : 'No' }}
                </td>
                <td class="{{ $student->has_first_communion ? 'badge-yes' : 'badge-no' }}">
                    {{ $student->has_first_communion ? 'Yes' : 'No' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center;">No Catholic students found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
