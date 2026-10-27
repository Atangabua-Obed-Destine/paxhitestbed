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

// how many rows does the table show?
$countRows = function (string $html) {
    return preg_match_all('~<td class="acct-when"~', $html);
};

foreach (['income' => 'App\Models\Income', 'expense' => 'App\Models\Expense'] as $screen => $model) {
    echo "\n=== /admin/account/{$screen} ===\n";

    $cats = DB::table($screen === 'income' ? 'incomes' : 'expenses')
        ->selectRaw('category_id, COUNT(*) c')->groupBy('category_id')
        ->orderByDesc('c')->limit(2)->pluck('category_id')->all();

    if (count($cats) < 2) { echo "  (not enough categories to test)\n"; continue; }

    [$a, $b] = [$cats[0], $cats[1]];
    $base = "/admin/account/{$screen}?all=1";

    $cases = [
        'no filter (all)'          => $base,
        "one category ({$a})"      => "{$base}&category%5B%5D={$a}",
        "one category ({$b})"      => "{$base}&category%5B%5D={$b}",
        "both ({$a} + {$b})"       => "{$base}&category%5B%5D={$a}&category%5B%5D={$b}",
        "OLD scalar link (?category={$a})" => "{$base}&category={$a}",
        'junk value (?category=0)' => "{$base}&category=0",
    ];

    $seen = [];
    foreach ($cases as $label => $uri) {
        [$status, $html] = $get($uri);
        $n = $countRows($html);
        $seen[$label] = $n;
        printf("  %-34s HTTP %d  %3d rows\n", $label, $status, $n);
    }

    // the arithmetic that matters: picking two must equal picking each
    $sum = $seen["one category ({$a})"] + $seen["one category ({$b})"];
    printf("  %-34s %d + %d = %d vs both=%d  %s\n", 'two categories == each added',
        $seen["one category ({$a})"], $seen["one category ({$b})"], $sum,
        $seen["both ({$a} + {$b})"],
        $sum === $seen["both ({$a} + {$b})"] ? 'OK' : 'MISMATCH');

    printf("  %-34s %s\n", 'old scalar link still filters',
        $seen["OLD scalar link (?category={$a})"] === $seen["one category ({$a})"] ? 'OK' : 'BROKEN');

    printf("  %-34s %s\n", 'category=0 means all, not nothing',
        $seen['junk value (?category=0)'] === $seen['no filter (all)'] ? 'OK' : 'BROKEN');

    // the markup
    [, $html] = $get("{$base}&category%5B%5D={$a}");
    printf("  %-34s %s\n", 'select is multiple',
        strpos($html, 'name="category[]"') !== false && strpos($html, 'multiple') !== false ? 'OK' : 'MISSING');
    printf("  %-34s %s\n", 'chosen category stays selected',
        preg_match('~<option value="'.$a.'" selected~', $html) ? 'OK' : 'MISSING');

    // the quick-range links must carry the choice forward
    printf("  %-34s %s\n", 'quick ranges keep the categories',
        strpos($html, 'category%5B0%5D=' . $a) !== false || strpos($html, 'category[0]=' . $a) !== false
            ? 'OK' : 'LOST');
}
