<?php

namespace App\Services\SMS;

use AfricasTalking\SDK\AfricasTalking;

class AfricasTalkingService implements SMSServiceInterface
{
    protected $client;

    public function __construct()
    {
        $AT = new AfricasTalking(env('AFRICASTALKING_USERNAME'), env('AFRICASTALKING_API_KEY'));
        $this->client = $AT->sms();
    }

    public function sendSMS($to, $message)
    {
        $this->client->send([
            'to' => $to,
            'message' => $message
        ]);
    }
}
