<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use App\Traits\EnvironmentVariable;
use App\Services\MailSettingTester;
use Illuminate\Http\Request;
use App\Models\MailSetting;

class MailSettingController extends Controller
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
        $this->title = trans_choice('module_mail_setting', 1);
        $this->route = 'admin.mail-setting';
        $this->view = 'admin.mail-setting';
        $this->access = 'setting';


        $this->middleware('permission:'.$this->access.'-mail');
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

        $data['row'] = MailSetting::where('status', '1')->first();

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
            'driver' => 'required',
            'host' => 'required',
            'port' => 'required',
            'username' => 'required',
            'password' => 'required',
            'encryption' => 'required',
            'sender_email' => 'required|email',
            'sender_name' => 'required',
            // 'reply_email' => 'required|email',
        ]);



        $id = $request->id;

        // -1 means no data row found
        if($id == -1){
            // Insert Data
            $input = $request->all();
            $data = MailSetting::create($input);
        }
        else{
            // Update Data
            $data = MailSetting::find($id);

            $input = $request->all();
            $data->update($input);
        }

        // Update to Env
        $this->updateEnvVariable('MAIL_DRIVER', '"'.$request->driver.'"' ?? '"none"');
        $this->updateEnvVariable('MAIL_HOST', '"'.$request->host.'"' ?? '"none"');
        $this->updateEnvVariable('MAIL_PORT', '"'.$request->port.'"' ?? '"none"');
        $this->updateEnvVariable('MAIL_USERNAME', '"'.$request->username.'"' ?? '"none"');
        $this->updateEnvVariable('MAIL_PASSWORD', '"'.$request->password.'"' ?? '"none"');
        $this->updateEnvVariable('MAIL_ENCRYPTION', '"'.$request->encryption.'"' ?? '"none"');


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Try the settings on screen and report how far they get.
     *
     * Takes the values from the form rather than from the saved row, so a
     * wrong password can be corrected and proved before it is saved over a
     * working one. Nothing here writes to the database or to .env.
     */
    public function test(Request $request, MailSettingTester $tester)
    {
        $request->validate([
            'driver' => 'required|in:smtp,sendmail',
            'test_email' => 'required|email',
        ]);

        $saved = MailSetting::where('status', '1')->first();

        $config = [
            'driver' => $request->input('driver'),
            'host' => trim((string) $request->input('host')),
            'port' => trim((string) $request->input('port')),
            'username' => trim((string) $request->input('username')),
            // An untouched password field posts the value already on screen.
            // Falling back to the saved one covers a browser that declines to
            // autofill it, so the test is not failed by a blank it invented.
            'password' => $request->filled('password')
                ? (string) $request->input('password')
                : (string) optional($saved)->password,
            'encryption' => (string) $request->input('encryption'),
            'sender_email' => trim((string) $request->input('sender_email')),
            'sender_name' => trim((string) $request->input('sender_name')),
            'reply_email' => trim((string) $request->input('reply_email')),
        ];

        return response()->json(
            $tester->run($config, trim((string) $request->input('test_email')))
        );
    }
}
