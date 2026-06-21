<?php

namespace App\Services\SMS;

use Twilio\Rest\Client;

class TwilioService implements SMSServiceInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    }

    public function sendSMS($to, $message)
    {
        $this->client->messages->create($to, [
            'from' => env('TWILIO_NUMBER'),
            'body' => $message
        ]);
    }
}
