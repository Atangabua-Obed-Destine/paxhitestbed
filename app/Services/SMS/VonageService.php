<?php

namespace App\Services\SMS;

use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class VonageService implements SMSServiceInterface
{
    protected $client;

    public function __construct()
    {
        $basic  = new Basic(env('VONAGE_KEY'), env('VONAGE_SECRET'));
        $this->client = new Client($basic);
    }

    public function sendSMS($to, $message)
    {
        $this->client->message()->send([
            'to' => $to,
            'from' => env('VONAGE_NUMBER'),
            'text' => $message
        ]);
    }
}
