<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$user = App\User::whereHas('roles', fn($q) => $q->where('id', 1))->first() ?: App\User::first();
Illuminate\Support\Facades\Auth::guard('web')->login($user);

$get = function (string $uri) use ($kernel) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);
    return [$response->getStatusCode(), $response->getContent()];
};

foreach (['income', 'expense'] as $mod) {
    $table = $mod === 'income' ? 'incomes' : 'expenses';
    $cats = DB::table($table)->selectRaw('category_id, COUNT(*) c')
        ->whereNotNull('category_id')->groupBy('category_id')
        ->orderByDesc('c')->limit(2)->pluck('category_id')->all();

    $cases = [
        'everything'   => "/admin/account/{$mod}/report?all=1",
        'one category' => "/admin/account/{$mod}/report?all=1&category%5B%5D={$cats[0]}",
        'two cats'     => "/admin/account/{$mod}/report?all=1&category%5B%5D={$cats[0]}&category%5B%5D={$cats[1]}",
        'a window'     => "/admin/account/{$mod}/report?start_date=2025-01-01&end_date=2025-12-31",
        'no matches'   => "/admin/account/{$mod}/report?all=1&title=" . urlencode('zzz-no-such-thing'),
    ];

    echo "\n=== /admin/account/{$mod}/report ===\n";

    foreach ($cases as $label => $uri) {
        [$status, $body] = $get($uri);
        $isPdf = substr($body, 0, 5) === '%PDF-';
        $file = __DIR__ . "/rep-{$mod}-" . preg_replace('~\W+~', '-', $label) . '.pdf';
        if ($isPdf) file_put_contents($file, $body);
        printf("  %-14s HTTP %d  %10s bytes  %s\n", $label, $status,
            number_format(strlen($body)), $isPdf ? 'real PDF' : 'NOT A PDF');
        if (!$isPdf && $status !== 200) {
            echo "      " . trim(strip_tags(substr($body, 0, 400))) . "\n";
        }
    }
}
