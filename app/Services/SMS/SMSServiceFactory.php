<?php

namespace App\Services\SMS;

class SMSServiceFactory
{
    public static function create($provider)
    {
        switch ($provider) {
            case '1':
                return app(TwilioService::class);
            case '2':
                return app(VonageService::class);
            case '3':
                return app(TextLocalService::class);
            case '4':
                return app(ClickatellService::class);
            case '5':
                return app(AfricasTalkingService::class);
            case '6':
                return app(SMSCountryService::class);
            default:
                throw new \Exception("SMS provider not supported.");
        }
    }
}
