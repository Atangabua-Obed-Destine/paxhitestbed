<?php

use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$session = $app->make('session');
$session->start();
$token = $session->token();

$request = Request::create('/application', 'POST', [
    'program' => 1,
    'first_name' => 'CLI',
    'last_name' => 'Tester',
    'email' => 'cli-tester@example.com',
    'phone' => '1234567890',
    'gender' => 1,
    'dob' => '2000-01-01',
    'password' => 'Password123',
    'password_confirmation' => 'Password123',
    'agree_terms' => 1,
    '_token' => $token,
]);

$request->server->set('HTTP_HOST', 'localhost');
$request->server->set('REMOTE_ADDR', '127.0.0.1');
$request->server->set('HTTP_USER_AGENT', 'CLI Debug');
$request->setLaravelSession($session);
$request->headers->set('X-CSRF-TOKEN', $token);

try {
    $response = $kernel->handle($request);
    echo 'Status: '.$response->getStatusCode().PHP_EOL;
    echo 'Content: '.PHP_EOL.$response->getContent().PHP_EOL;
} catch (Throwable $e) {
    echo 'Exception: '.$e->getMessage().PHP_EOL;
    echo $e->getTraceAsString();
} finally {
    $kernel->terminate($request, isset($response) ? $response : null);
}
