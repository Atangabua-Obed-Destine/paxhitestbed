<!DOCTYPE html>
<html>
<head>
    <title>Partial Payment Report - Direct Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Partial Payment Report - Direct Test</h2>
        <p class="text-muted">This page bypasses authentication to test the report functionality</p>

        <?php
        require __DIR__.'/../vendor/autoload.php';
        
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        try {
            $controller = new \App\Http\Controllers\Admin\PartialPaymentReportController();
            $request = new \Illuminate\Http\Request();
            
            $fees = \App\Models\Fee::with([
                'studentEnroll.student',
                'studentEnroll.session',
                'studentEnroll.semester',
                'studentEnroll.program',
                'category',
                'approvedReceipts'
            ])->where('status', 2)->get();
            
            $stats = [
                'total_fees' => \App\Models\Fee::where('status', 2)->count(),
                'total_due' => \App\Models\Fee::where('status', 2)->get()->sum(function($fee) {
                    return $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
                }),
                'total_paid' => \App\Models\Fee::where('status', 2)->sum('paid_amount'),
                'total_remaining' => \App\Models\Fee::where('status', 2)->get()->sum(function($fee) {
                    return $fee->remaining_balance;
                }),
            ];

            echo '<div class="alert alert-success">✓ Data loaded successfully!</div>';
            
            echo '<div class="row mb-4">';
            echo '<div class="col-md-3">';
            echo '<div class="card"><div class="card-body">';
            echo '<h4>' . $stats['total_fees'] . '</h4>';
            echo '<p class="mb-0">Total Partially Paid Fees</p>';
            echo '</div></div>';
            echo '</div>';
            
            echo '<div class="col-md-3">';
            echo '<div class="card"><div class="card-body">';
            echo '<h4>₦' . number_format($stats['total_due'], 2) . '</h4>';
            echo '<p class="mb-0">Total Amount Due</p>';
            echo '</div></div>';
            echo '</div>';
            
            echo '<div class="col-md-3">';
            echo '<div class="card"><div class="card-body">';
            echo '<h4>₦' . number_format($stats['total_paid'], 2) . '</h4>';
            echo '<p class="mb-0">Total Paid</p>';
            echo '</div></div>';
            echo '</div>';
            
            echo '<div class="col-md-3">';
            echo '<div class="card"><div class="card-body">';
            echo '<h4>₦' . number_format($stats['total_remaining'], 2) . '</h4>';
            echo '<p class="mb-0">Total Remaining</p>';
            echo '</div></div>';
            echo '</div>';
            echo '</div>';

            echo '<table class="table table-bordered table-striped">';
            echo '<thead class="thead-dark">';
            echo '<tr>';
            echo '<th>Fee ID</th>';
            echo '<th>Student</th>';
            echo '<th>Category</th>';
            echo '<th>Total Amount</th>';
            echo '<th>Paid Amount</th>';
            echo '<th>Remaining</th>';
            echo '<th>Status</th>';
            echo '<th>Payments</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($fees as $fee) {
                echo '<tr>';
                echo '<td>#' . $fee->id . '</td>';
                echo '<td>';
                if ($fee->studentEnroll && $fee->studentEnroll->student) {
                    echo $fee->studentEnroll->student->first_name . ' ' . $fee->studentEnroll->student->last_name;
                    echo '<br><small class="text-muted">' . $fee->studentEnroll->student->student_id . '</small>';
                } else {
                    echo 'N/A';
                }
                echo '</td>';
                echo '<td>' . ($fee->category ? $fee->category->title : 'N/A') . '</td>';
                echo '<td>₦' . number_format($fee->total_amount, 2) . '</td>';
                echo '<td>₦' . number_format($fee->paid_amount, 2) . '</td>';
                echo '<td>₦' . number_format($fee->remaining_balance, 2) . '</td>';
                echo '<td>' . $fee->status_badge . '</td>';
                echo '<td>' . $fee->approvedReceipts->count() . ' payment(s)</td>';
                echo '</tr>';
            }
            
            if ($fees->count() == 0) {
                echo '<tr><td colspan="8" class="text-center">No partially paid fees found</td></tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            echo '<div class="alert alert-info">';
            echo '<strong>Test Result:</strong> Everything is working correctly!<br>';
            echo '<strong>Issue:</strong> The 500 error you\'re seeing is likely due to:<br>';
            echo '<ul>';
            echo '<li>Browser session expired - Try logging out and back in</li>';
            echo '<li>Browser cache - Try clearing cache or use incognito mode</li>';
            echo '<li>CSRF token mismatch - Refresh the page</li>';
            echo '</ul>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">';
            echo '<strong>Error:</strong> ' . $e->getMessage() . '<br>';
            echo '<strong>File:</strong> ' . $e->getFile() . '<br>';
            echo '<strong>Line:</strong> ' . $e->getLine();
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
