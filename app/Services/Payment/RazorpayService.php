<?php

namespace App\Services\Payment;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\BadRequestError;

class RazorpayService
{
    protected $api;

    public function __construct()
    {
        $this->api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
    }

    public function createOrder($amount, $currency)
    {
        try {
            $order = $this->api->order->create([
                'amount' => $amount * 100,
                'currency' => $currency,
            ]);

            return $order->id;
            
        } catch (BadRequestError $e) {
            throw new \Exception('Razorpay Error: ' . $e->getMessage());
        }
    }

    public function capturePayment($paymentId, $amount)
    {
        return $this->api->payment->fetch($paymentId)->capture(['amount' => $amount * 100]);
    }
}
