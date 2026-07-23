<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$request = Illuminate\Http\Request::create('/');
$kernel->handle($request);

use App\Models\Grade;

$sessionId = 2;
$semesterId = 1;

$controller = new \App\Http\Controllers\Admin\SenateDeliberationController();
// Use reflection to call private method
$reflection = new \ReflectionClass(\App\Http\Controllers\Admin\SenateDeliberationController::class);
$method = $reflection->getMethod('buildStudentMatrixByFaculty');
$method->setAccessible(true);

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
$matrices = $method->invokeArgs($controller, [$sessionId, $semesterId, null, $grades]);

foreach ($matrices as $facData) {
    echo "--- Faculty: {$facData['shortcode']} ---" . PHP_EOL;
    $facRegistered = 0;
    $facPassed = 0;
    $facFailed = 0;
    
    foreach ($facData['programs'] as $prog) {
        $facRegistered += $prog['overall']['total_students'];
        $facPassed += $prog['overall']['total_passed'];
        $facFailed += $prog['overall']['total_failed'];
    }
    
    echo "  No Registered: {$facRegistered}" . PHP_EOL;
    echo "  No Passed: {$facPassed}" . PHP_EOL;
    echo "  No Failed: {$facFailed}" . PHP_EOL;
}
