<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sessions = App\Models\Session::orderBy('id', 'desc')->get();
echo "Available Sessions:\n";
foreach($sessions as $s) {
    echo "  ID: {$s->id} - {$s->title}\n";
}
