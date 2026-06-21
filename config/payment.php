<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Payment
	| Get variable from env
	|--------------------------------------------------------------------------
	*/

    'status' => env('PAYMENT_GATEWAY'),
    
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'mode' => env('PAYPAL_MODE'),
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
    ],
    'paystack' => [
        'key' => env('PAYSTACK_KEY'),
        'secret' => env('PAYSTACK_SECRET'),
        'email' => env('MERCHANT_EMAIL'),
    ],
    'flutterwave' => [
        'key' => env('FLW_PUBLIC_KEY'),
        'secret' => env('FLW_SECRET_KEY'),
        'hash' => env('FLW_SECRET_HASH'),
    ],
    'skrill' => [
        'email' => env('SKRILL_EMAIL'),
        'secret' => env('SKRILL_SECRET'),
    ],
);