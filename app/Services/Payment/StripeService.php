<?php

namespace App\Services\Payment;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function createPaymentIntent($amount, $currency)
    {
        try {
            return PaymentIntent::create([
                'amount' => $amount * 100,
                'currency' => $currency,
            ]);

        } catch (ApiErrorException $e) {
            throw new \Exception('Stripe Error: ' . $e->getMessage());
        }
    }
}
