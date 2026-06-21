<?php

namespace App\Services\SMS;

use Clickatell\Rest;

class ClickatellService implements SMSServiceInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new Rest(env('CLICKATELL_API_KEY'));
    }

    public function sendSMS($to, $message)
    {
        $this->client->sendMessage(['to' => [$to], 'content' => $message]);
    }
}
