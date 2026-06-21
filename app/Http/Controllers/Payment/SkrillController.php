<?php

namespace App\Http\Controllers\Payment;

use Illuminate\Support\Facades\Redirect;
use App\Http\Controllers\Controller;
use Obydul\LaraSkrill\SkrillRequest;
use Obydul\LaraSkrill\SkrillClient;
use Illuminate\Http\Request;

class SkrillController extends Controller
{
    /**
     * Construct.
     */
    private $skrilRequest;

    public function __construct()
    {
        // skrill config
        $this->skrilRequest = new SkrillRequest();
        $this->skrilRequest->pay_to_email = env('SKRILL_EMAIL');
        $this->skrilRequest->return_url = route('payment.skrill.completed');
        $this->skrilRequest->cancel_url = route('payment.skrill.cancelled');
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('skrill');
    }

    /**
     * Make Payment
     */
    public function makePayment()
    {
        // create object instance of SkrillRequest
        $this->skrilRequest->prepare_only = 1;
        $this->skrilRequest->amount = '10.50';
        $this->skrilRequest->currency = 'USD';
        $this->skrilRequest->language = 'EN';

        // create object instance of SkrillClient
        $client = new SkrillClient($this->skrilRequest);
        $sid = $client->generateSID(); //return SESSION ID

        // handle error
        $jsonSID = json_decode($sid);
        if ($jsonSID != null && $jsonSID->code == "BAD_REQUEST")
            return $jsonSID->message;

        // do the payment
        $redirectUrl = $client->paymentRedirectUrl($sid); //return redirect url
        return Redirect::to($redirectUrl); // redirect user to Skrill payment page
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function paymentCompleted()
    {
        return view('success');
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function paymentCancelled()
    {
        return view('cancel');
    }
}
