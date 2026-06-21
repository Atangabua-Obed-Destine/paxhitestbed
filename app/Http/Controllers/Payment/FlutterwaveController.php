<?php

namespace App\Http\Controllers\Payment;

use KingFlamez\Rave\Facades\Rave as Flutterwave;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Traits\FeesStudent;
use App\Models\Setting;
use App\Models\Fee;

class FlutterwaveController extends Controller
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
        return view('flutterwave');
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function process(Request $request)
    {
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


        // Initialize the payment
        $reference = Flutterwave::generateReference();
        $data = [
            'payment_options' => 'card,banktransfer',
            'amount' => $amount,
            'email' => Auth::guard('student')->user()->email,
            'tx_ref' => $reference,
            'currency' => $currency ?? 'NGN',
            'redirect_url' => route('payment.flutterwave.callback'),
            'customer' => [
                'email' => Auth::guard('student')->user()->email,
                'name' => Auth::guard('student')->user()->first_name,
            ],
            'customizations' => [
                'title' => 'Student Fee',
                'description' => __('field_receipt') .': '. ($print->prefix ?? '') . str_pad($fee_id, 6, '0', STR_PAD_LEFT),
            ],
            'meta' => [
                'fee_id' => $fee_id,
            ],
        ];

        // Charge Fee If Status Is Unpaid or Partially Paid
        if(isset($fee->status) && ($fee->status == 0 || $fee->status == 2)){

            $payment = Flutterwave::initializePayment($data);

            if(isset($payment)){
                if ($payment['status'] !== 'success') {

                    Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

                    return redirect()->back();
                }

                return redirect($payment['data']['link']);
            }
        }

        Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

        return redirect()->back();
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function callback()
    {
        try {
            $status = request()->status;

            if ($status == 'successful') {
                $transactionID = Flutterwave::getTransactionIDFromCallback();
                $data = Flutterwave::verifyTransaction($transactionID);

                $fee_id = $data['data']['meta']['fee_id'] ?? null;

                // Update Fee
                if(isset($fee_id)){
                    $this->payStudentFee($fee_id, 10);
                }

                Flasher::addSuccess(__('msg_your_payment_successful'), __('msg_success'));

            } elseif ($status == 'cancelled') {

                Flasher::addError(__('msg_your_payment_cancelled'), __('msg_error'));

            } else {

                Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));
            }

            return redirect()->route('student.fees.index');

        } catch (\Exception $e) {

            Log::error('Flutterwave Callback Error: ' . $e->getMessage());

            Flasher::addError(__('msg_something_went_wrong'), __('msg_error'));

            return redirect()->route('student.fees.index');
        }
    }
}
