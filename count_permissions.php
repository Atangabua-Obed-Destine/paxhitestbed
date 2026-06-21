<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$count = Spatie\Permission\Models\Permission::count();
echo "Total permissions in database: $count\n";
echo "Target: 686\n";
echo "Gap: " . (686 - $count) . "\n";
