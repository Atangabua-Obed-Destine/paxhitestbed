<?php

namespace App\Services\Payment;

use Obydul\Larapaypal\Paypal;

class PayPalService
{
    protected $paypal;

    public function __construct()
    {
        $this->paypal = new Paypal([
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'mode' => env('PAYPAL_MODE'),
        ]);
    }

    public function createPayment($amount, $currency)
    {
        $response = $this->paypal->create([
            'amount' => $amount,
            'currency' => $currency,
            'return_url' => route('payment.success'),
            'cancel_url' => route('payment.cancel'),
        ]);

        if ($response['status'] == 'success') {
            // Redirect the user to the PayPal approval URL
            return redirect($response['approval_url']);
        } else {
            // Handle payment creation failure
            return back()->withErrors($response['message']);
        }
    }

    public function executePayment($paymentId, $payerId)
    {
        // Execute the payment after the user has approved the payment
        $response = $this->paypal->execute($paymentId, $payerId);

        if ($response['status'] == 'success') {
            return $response;
        } else {
            // Handle payment execution failure
            return back()->withErrors($response['message']);
        }
    }
}
