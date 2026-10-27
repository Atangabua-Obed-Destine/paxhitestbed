<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$lh = app(App\Services\LetterheadService::class);

$html = $lh->render(true);

echo "--- render(true) image srcs ---\n";
if (preg_match_all('~<img[^>]*src="([^"]{0,160})~i', $html, $m)) {
    foreach ($m[1] as $src) {
        $shown = strlen($src) > 90 ? substr($src, 0, 90) . '...' : $src;
        echo "  {$shown}\n";
        if (!str_starts_with($src, 'data:')) {
            echo "      exists on disk? " . (is_file($src) ? 'YES' : 'NO') . "\n";
        }
    }
} else {
    echo "  (no <img> at all)\n";
}

echo "\n--- background-image urls ---\n";
if (preg_match_all('~url\((["\']?)([^)"\']{0,160})~i', $lh->styles() . $html, $m)) {
    foreach (array_unique($m[2]) as $src) {
        $shown = strlen($src) > 90 ? substr($src, 0, 90) . '...' : $src;
        echo "  {$shown}\n";
        if (!str_starts_with($src, 'data:')) {
            echo "      exists on disk? " . (is_file($src) ? 'YES' : 'NO') . "\n";
        }
    }
} else {
    echo "  (none)\n";
}

echo "\n--- render(false) for comparison ---\n";
if (preg_match_all('~<img[^>]*src="([^"]{0,110})~i', $lh->render(false), $m)) {
    foreach ($m[1] as $src) echo "  " . (strlen($src) > 90 ? substr($src, 0, 90) . '...' : $src) . "\n";
}
