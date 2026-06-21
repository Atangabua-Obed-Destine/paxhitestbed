<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Traits\FeesStudent;
use App\Models\Setting;
use App\Models\Fee;
use Paystack;

class PaystackController extends Controller
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
        return view('paystack');
    }

    /**
     * Redirect the User to Paystack Payment Page
     * @return Url
     */
    public function redirectToGateway(Request $request)
    {
        // Payment Process
        try {

            // Get Amount
            $amount = 0;
            if(isset($request->fee_id)){
                $fee_id = $request->fee_id;

                $amount = $this->netAmount($fee_id);
            }

            //
            $currency = Setting::where('status', '1')->first()->currency ?? 'NGN';
            $print = PrintSetting::where('slug', 'fees-receipt')->first();
            $fee = Fee::where('id', $fee_id)->first();

            $data = array(
                "amount" => $amount * 100,
                "reference" => Paystack::genTranxRef(),
                "email" => Auth::guard('student')->user()->email,
                "currency" => $currency ?? 'NGN',
                "orderID" => str_pad($fee_id, 6, '0', STR_PAD_LEFT),
                "callback_url" => route('payment.paystack.callback'),
                "metadata" => [
                    "fee_id" => $fee_id,
                    "description" => __('field_receipt') .': '. ($print->prefix ?? '') . str_pad($fee_id, 6, '0', STR_PAD_LEFT),
                ],
            );

            // Charge Fee If Status Is Unpaid or Partially Paid
            if(isset($fee->status) && ($fee->status == 0 || $fee->status == 2)){

                return Paystack::getAuthorizationUrl($data)->redirectNow();
            }
            else {

                Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

                return redirect()->back();
            }

        } catch(\Exception $e) {

            \Log::error('PayStack Payment Error: ' . $e->getMessage());

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Obtain Paystack payment information
     * @return void
     */
    public function handleGatewayCallback()
    {
        try {
            $paymentDetails = Paystack::getPaymentData();

            // Check if payment was successful
            if (isset($paymentDetails['data']['status']) && $paymentDetails['data']['status'] == 'success') {

                // Get fee ID
                $fee_id = $paymentDetails['data']['metadata']['fee_id'];

                // Update Fee
                if(isset($fee_id)){
                    $this->payStudentFee($fee_id, 9);
                }

                Flasher::addSuccess(__('msg_your_payment_successful'), __('msg_success'));

            } else {

                Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));
            }

            return redirect()->route('student.fees.index');

        } catch (\Exception $e) {

            \Log::error('PayStack Payment Error: ' . $e->getMessage());

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->route('student.fees.index');
        }
    }
}
