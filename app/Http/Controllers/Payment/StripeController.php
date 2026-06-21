<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Traits\FeesStudent;
use App\Models\Setting;
use App\Models\Fee;
use Stripe\Stripe;
use Stripe\Charge;

class StripeController extends Controller
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
        return view('stripe');
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function process(Request $request)
    {
        //
        try {
            // Payment Process
            Stripe::setApiKey(env('STRIPE_SECRET'));

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


            // Charge Fee If Status Is Unpaid or Partially Paid
            if(isset($fee->status) && ($fee->status == 0 || $fee->status == 2)){
                $charge = Charge::create ([
                    "amount" => $amount * 100,
                    "currency" => $currency ?? 'USD',
                    "source" => $request->stripeToken,
                    "description" => __('field_receipt') .': '. ($print->prefix ?? '') . str_pad($fee_id, 6, '0', STR_PAD_LEFT),
                ]);
            }


            if (isset($charge) && $charge->status == 'succeeded') {

                // Update Fee
                if(isset($request->fee_id)){
                    $this->payStudentFee($fee_id, 7);
                }

                Flasher::addSuccess(__('msg_your_payment_successful'), __('msg_success'));

                return redirect()->route('student.fees.index');

            } else {

                Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));
            }

            return redirect()->back();

        } catch(\Exception $e) {

            Log::error('Stripe Payment Error: ' . $e->getMessage());

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->back();
        }

    }
}
