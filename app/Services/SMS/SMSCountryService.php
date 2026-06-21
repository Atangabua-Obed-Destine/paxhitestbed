<?php

namespace App\Services\SMS;

use Namshi\SMSCountry\Client;

class SMSCountryService implements SMSServiceInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(env('SMSCOUNTRY_USER'), env('SMSCOUNTRY_PASSWORD'), env('SMSCOUNTRY_SENDER_ID'));
    }

    public function sendSMS($to, $message)
    {
        $this->client->sendSms($to, $message);
    }
}
