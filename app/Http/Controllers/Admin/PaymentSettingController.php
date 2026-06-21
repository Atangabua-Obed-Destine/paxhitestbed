<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use App\Traits\EnvironmentVariable;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    use EnvironmentVariable;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_payment_setting', 1);
        $this->route = 'admin.payment-setting';
        $this->view = 'admin.payment-setting';
        $this->access = 'setting';


        $this->middleware('permission:'.$this->access.'-payment');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        return view($this->view.'.index', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'status' => 'required',
        ]);


        // Update to Env
        $this->updateEnvVariable('PAYMENT_GATEWAY', '"'.$request->status.'"' ?? '"none"');

        $this->updateEnvVariable('PAYPAL_CLIENT_ID', '"'.$request->paypal_client_id.'"' ?? '"none"');
        $this->updateEnvVariable('PAYPAL_SECRET', '"'.$request->paypal_secret.'"' ?? '"none"');

        $this->updateEnvVariable('STRIPE_KEY', '"'.$request->stripe_key.'"' ?? '"none"');
        $this->updateEnvVariable('STRIPE_SECRET', '"'.$request->stripe_secret.'"' ?? '"none"');

        $this->updateEnvVariable('RAZORPAY_KEY', '"'.$request->razorpay_key.'"' ?? '"none"');
        $this->updateEnvVariable('RAZORPAY_SECRET', '"'.$request->razorpay_secret.'"' ?? '"none"');

        $this->updateEnvVariable('PAYSTACK_KEY', '"'.$request->paystack_key.'"' ?? '"none"');
        $this->updateEnvVariable('PAYSTACK_SECRET', '"'.$request->paystack_secret.'"' ?? '"none"');
        $this->updateEnvVariable('MERCHANT_EMAIL', '"'.$request->paystack_email.'"' ?? '"none"');

        $this->updateEnvVariable('FLW_PUBLIC_KEY', '"'.$request->flutterwave_key.'"' ?? '"none"');
        $this->updateEnvVariable('FLW_SECRET_KEY', '"'.$request->flutterwave_secret.'"' ?? '"none"');
        $this->updateEnvVariable('FLW_SECRET_HASH', '"'.$request->flutterwave_hash.'"' ?? '"none"');

        $this->updateEnvVariable('SKRILL_EMAIL', '"'.$request->skrill_email.'"' ?? '"none"');
        $this->updateEnvVariable('SKRILL_SECRET', '"'.$request->skrill_secret.'"' ?? '"none"');


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
