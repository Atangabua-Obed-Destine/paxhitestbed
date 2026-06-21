<?php

namespace App\Services\Payment;

use Obydul\Laraskrill\SkrillClient;
use Obydul\Laraskrill\SkrillRequest;

class SkrillService
{
    protected $client;

    public function __construct()
    {
        $this->client = new SkrillClient(env('SKRILL_EMAIL'), env('SKRILL_SECRET'));
    }

    public function createPayment($amount, $currency, $email)
    {
        try {
            $request = new SkrillRequest();
            $request->pay_to_email = env('SKRILL_EMAIL');
            $request->amount = $amount;
            $request->currency = $currency;
            $request->customer_email = $email;
            $request->return_url = route('payment.success');
            $request->cancel_url = route('payment.cancel');

            return $this->client->generateRedirectUrl($request);

            /*$response = $this->client->pay($request);

            return $response->getRedirectUrl();*/

            /*$client = new SkrillClient($request);
            $sid = $client->generateSID();
            $redirectUrl = $client->paymentRedirectUrl($sid);

            return redirect()->to($redirectUrl); */

        } catch (\Exception $e) {
            throw new \Exception('Skrill Error: ' . $e->getMessage());
        }
    }
}
