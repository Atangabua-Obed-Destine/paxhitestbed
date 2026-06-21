<?php

namespace App\Services\Payment;

use Unicodeveloper\Paystack\Paystack;

class PaystackService
{
    protected $paystack;

    public function __construct()
    {
        $this->paystack = new Paystack();
    }

    public function createPayment($amount, $email)
    {
        try {
            $response = $this->paystack->transaction->initialize([
                'amount' => $amount * 100,
                'email' => $email,
                'callback_url' => route('payment.success'),
            ]);

            return $response['data']['authorization_url'];

        } catch (\Exception $e) {
            throw new \Exception('Skrill Error: ' . $e->getMessage());
        }
    }
}
