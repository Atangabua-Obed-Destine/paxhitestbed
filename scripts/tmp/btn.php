<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::guard('web')->login(App\User::first());
foreach ([
  'income plain' => '/admin/account/income?all=1',
  'income filtered' => '/admin/account/income?all=1&category%5B%5D=6&category%5B%5D=5&title=fee',
  'expense filtered' => '/admin/account/expense?start_date=2025-01-01&end_date=2025-12-31&category%5B%5D=5',
] as $label => $uri) {
    $r = Illuminate\Http\Request::create($uri, 'GET');
    $r->setLaravelSession(app('session.store'));
    $html = $kernel->handle($r)->getContent();
    preg_match_all('~<a href="([^"]*account/(?:income|expense)/report[^"]*)"~', $html, $m);
    printf("%-18s %s\n", $label, html_entity_decode($m[1][0] ?? "NO REPORT LINK"));
}
