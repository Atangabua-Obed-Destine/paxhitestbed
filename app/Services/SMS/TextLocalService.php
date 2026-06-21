<?php

namespace App\Services\SMS;

use Textlocal\Textlocal;

class TextLocalService implements SMSServiceInterface
{
    protected $client;

    public function __construct()
    {
        $this->client = new Textlocal(false, false, env('TEXT_LOCAL_KEY'));
    }

    public function sendSMS($to, $message)
    {
        $numbers = explode(',', $to);
        $sender = env('TEXT_LOCAL_SENDER');
        $this->client->send($message, $numbers, $sender);
    }
}
