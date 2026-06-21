<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Srmklive\PayPal\Services\PayPal;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Traits\FeesStudent;
use App\Models\Setting;
use App\Models\Fee;

class PaypalController extends Controller
{
    use FeesStudent;

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        return view('paypal');
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function process(Request $request)
    {
        // Payment Process
        $provider = new PayPal;
        $provider->setApiCredentials(config('paypal'));
        $paypalToken = $provider->getAccessToken();


        // Get Amount
        $amount = 0;
        if(isset($request->fee_id)){
            $fee_id = $request->fee_id;

            $amount = $this->netAmount($fee_id);
        }

        //
        $currency = Setting::where('status', '1')->first()->currency ?? 'USD';
        $print = PrintSetting::where('slug', 'fees-receipt')->first();
        $fee = Fee::where('id', $fee_id)->first();

        // Set Session
        session()->put('fee_id', $fee_id);


        // Charge Fee If Status Is Unpaid or Partially Paid
        if(isset($fee->status) && ($fee->status == 0 || $fee->status == 2)){
            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('payment.paypal.success'),
                    "cancel_url" => route('payment.paypal.cancel'),
                ],
                "purchase_units" => [
                    0 => [
                        "amount" => [
                            "currency_code" => $currency ?? 'USD',
                            "value" => $amount,
                        ],
                        "description" => __('field_receipt') .': '. ($print->prefix ?? '') . str_pad($fee_id, 6, '0', STR_PAD_LEFT),
                    ]
                ]
            ]);
        }


        if (isset($response['id']) && $response['id'] != null) {

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->route('payment.paypal.cancel');

        } else {

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->route('student.fees.index');
        }
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function paymentSuccess(Request $request)
    {
        // Payment Process
        $provider = new PayPal;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);

        if (isset($response['status']) && $response['status'] == 'COMPLETED') {

            // Update Fee
            $fee_id = session()->get('fee_id');

            if(isset($fee_id)){
                $this->payStudentFee($fee_id, 6);
            }

            // Clear Session
            session()->forget('fee_id');

            Flasher::addSuccess(__('msg_your_payment_successful'), __('msg_success'));

            return redirect()->route('student.fees.index');

        } else {

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->route('student.fees.index');
        }
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function paymentCancel()
    {
        //
        Flasher::addError(__('msg_your_payment_cancelled'), __('msg_error'));

        return redirect()->route('student.fees.index');
    }
}
