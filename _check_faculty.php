<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach(App\Models\Faculty::where('status',1)->withCount('programs')->get() as $f) {
    echo $f->id . ': ' . $f->title . ' (' . $f->programs_count . ' programs) | slug=' . $f->slug . PHP_EOL;
    echo '   desc: ' . substr(strip_tags($f->description ?? ''), 0, 80) . PHP_EOL;
}
