<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Fixing permissions with empty group and title...\n\n";

// Fix Results Summary permissions
DB::table('permissions')
    ->where('name', 'results-summary-view')
    ->update(['group' => 'Results Summary', 'title' => 'View']);

DB::table('permissions')
    ->where('name', 'results-summary-export')
    ->update(['group' => 'Results Summary', 'title' => 'Export']);

echo "Fixed: results-summary-view and results-summary-export -> Group: 'Results Summary'\n";

// Fix Dynamic Popup permissions
DB::table('permissions')
    ->where('name', 'dynamic-popup-view')
    ->update(['group' => 'Dynamic Popup', 'title' => 'View']);

DB::table('permissions')
    ->where('name', 'dynamic-popup-create')
    ->update(['group' => 'Dynamic Popup', 'title' => 'Create']);

DB::table('permissions')
    ->where('name', 'dynamic-popup-edit')
    ->update(['group' => 'Dynamic Popup', 'title' => 'Edit']);

DB::table('permissions')
    ->where('name', 'dynamic-popup-delete')
    ->update(['group' => 'Dynamic Popup', 'title' => 'Delete']);

echo "Fixed: dynamic-popup-* permissions -> Group: 'Dynamic Popup'\n";

echo "\nVerifying fix...\n\n";

$perms = DB::table('permissions')
    ->whereIn('name', [
        'results-summary-view',
        'results-summary-export',
        'dynamic-popup-view',
        'dynamic-popup-create',
        'dynamic-popup-edit',
        'dynamic-popup-delete',
    ])
    ->get(['id', 'name', 'group', 'title']);

foreach ($perms as $p) {
    echo "ID: {$p->id}, Name: {$p->name}, Group: [{$p->group}], Title: [{$p->title}]\n";
}

echo "\nDone! Please refresh the role create page.\n";
