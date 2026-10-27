<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\User::whereHas('roles', fn($q) => $q->where('id', 1))->first() ?: App\User::first();
Illuminate\Support\Facades\Auth::guard('web')->login($user);

$session = app('session.store');
$session->start();
$token = $session->token();

$request = Illuminate\Http\Request::create(
    '/admin/daybook/analysis/pdf', 'POST', ['budget_id' => 3, '_token' => $token]
);
$request->setLaravelSession($session);
$request->headers->set('X-CSRF-TOKEN', $token);

$response = $kernel->handle($request);
$body = $response->getContent();

file_put_contents(__DIR__ . '/daybook-pack.pdf', $body);
printf("daybook pack: HTTP %d  %s bytes  %s\n", $response->getStatusCode(),
    number_format(strlen($body)), substr($body, 0, 5) === '%PDF-' ? 'real PDF' : 'NOT A PDF');
