<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Traits\FeesStudent;
use App\Models\Setting;
use Razorpay\Api\Api;
use App\Models\Fee;
use Exception;

class RazorpayController extends Controller
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
        return view('razorpay');
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function process(Request $request)
    {
        // Payment Process
        $input = $request->all();

        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

        //
        $currency = Setting::where('status', '1')->first()->currency ?? 'INR';
        $fee = Fee::where('id', $request->fee_id)->first();

        // Charge Fee If Status Is Unpaid or Partially Paid
        if(isset($fee->status) && ($fee->status == 0 || $fee->status == 2)){
            if(count($input) && !empty($input['razorpay_payment_id'])) {

                try {

                    // Fetch the payment from Razorpay
                    $payment = $api->payment->fetch($input['razorpay_payment_id']);

                    // Capture the payment
                    $response = $payment->capture(['amount' => $payment->amount, 'currency' => $currency]);

                    if ($response->status == 'captured') {

                        // Update Fee
                        if(isset($request->fee_id)){
                            $this->payStudentFee($request->fee_id, 8);
                        }

                        Flasher::addSuccess(__('msg_your_payment_successful'), __('msg_success'));

                        return redirect()->back();
                    }
                    else {

                        Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

                        return redirect()->back();
                    }

                } catch (Exception $e) {

                    Log::error('Razorpay Payment Error: ' . $e->getMessage());

                    Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

                    return redirect()->back();
                }
            }
        }

        return redirect()->back();
    }
}
