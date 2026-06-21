<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\EnvironmentVariable;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\SMSSetting;

class SMSSettingController extends Controller
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
        $this->title = trans_choice('module_sms_setting', 1);
        $this->route = 'admin.sms-setting';
        $this->view = 'admin.sms-setting';
        $this->access = 'setting';


        $this->middleware('permission:'.$this->access.'-sms');
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

        $data['row'] = SMSSetting::first();

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



        $id = $request->id;

        // -1 means no data row found
        if($id == -1){
            // Insert Data
            $input = $request->all();
            $data = SMSSetting::create($input);
        }
        else{
            // Update Data
            $data = SMSSetting::find($id);

            $input = $request->all();
            $data->update($input);
        }

        // Update to Env
        $this->updateEnvVariable('SMS_GATEWAY', '"'.$request->status.'"' ?? '"none"');


        $this->updateEnvVariable('VONAGE_KEY', '"'.$request->vonage_key.'"' ?? '"none"');
        $this->updateEnvVariable('VONAGE_SECRET', '"'.$request->vonage_secret.'"' ?? '"none"');
        $this->updateEnvVariable('VONAGE_NUMBER', '"'.$request->vonage_number.'"' ?? '"none"');

        $this->updateEnvVariable('TWILIO_SID', '"'.$request->twilio_sid.'"' ?? '"none"');
        $this->updateEnvVariable('TWILIO_AUTH_TOKEN', '"'.$request->twilio_auth_token.'"' ?? '"none"');
        $this->updateEnvVariable('TWILIO_NUMBER', '"'.$request->twilio_number.'"' ?? '"none"');

        $this->updateEnvVariable('AFRICASTALKING_USERNAME', '"'.$request->africas_talking_username.'"' ?? '"none"');
        $this->updateEnvVariable('AFRICASTALKING_API_KEY', '"'.$request->africas_talking_key.'"' ?? '"none"');

        $this->updateEnvVariable('TEXT_LOCAL_KEY', '"'.$request->textlocal_key.'"' ?? '"none"');
        $this->updateEnvVariable('TEXT_LOCAL_SENDER', '"'.$request->textlocal_sender.'"' ?? '"none"');

        $this->updateEnvVariable('CLICKATELL_API_KEY', '"'.$request->clickatell_key.'"' ?? '"none"');

        $this->updateEnvVariable('SMSCOUNTRY_USER', '"'.$request->sms_country_username.'"' ?? '"none"');
        $this->updateEnvVariable('SMSCOUNTRY_PASSWORD', '"'.$request->sms_country_password.'"' ?? '"none"');
        $this->updateEnvVariable('SMSCOUNTRY_SENDER_ID', '"'.$request->sms_country_sender_id.'"' ?? '"none"');


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
