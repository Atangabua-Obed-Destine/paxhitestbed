<?php

namespace App\Services\SMS;

interface SMSServiceInterface
{
    public function sendSMS($to, $message);
}
