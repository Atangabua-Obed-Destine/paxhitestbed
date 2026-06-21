<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| SMS
	| Get variable from env
	|--------------------------------------------------------------------------
	*/
	
    'status' => env('SMS_GATEWAY'),

    'vonage' => [
        'key' => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'number' => env('VONAGE_NUMBER'),
    ],
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'number' => env('TWILIO_NUMBER'),
    ],
    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME'),
        'key' => env('AFRICASTALKING_API_KEY'),
    ],
    'textlocal' => [
        'key' => env('TEXT_LOCAL_KEY'),
        'sender' => env('TEXT_LOCAL_SENDER'),
    ],
    'clickatell' => [
        'key' => env('CLICKATELL_API_KEY'),
    ],
    'smscountry' => [
        'user' => env('SMSCOUNTRY_USER'),
        'password' => env('SMSCOUNTRY_PASSWORD'),
        'sender_id' => env('SMSCOUNTRY_SENDER_ID'),
    ],
);