<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\EnvironmentVariable;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

class PurchaseVerificationController extends Controller
{
    use EnvironmentVariable;

    protected $title, $route, $view, $path;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = 'Envato Purchase Verification';
        $this->route    = 'verify';
        $this->view     = 'verify';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;

        return view($this->view, $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        // Field Validation
        $request->validate([
            'purchase_code' => 'required',
        ]);

        $license = str_replace("-", "", $request->purchase_code);

        if(strlen($license) == 32){

            // Update to Env
            $this->updateEnvVariable('ENVATO_LICENSE', '"'.$license.'"' ?? '"none"');


            Flasher::addSuccess(__('msg_your_license_verified'), __('msg_success'));

            return redirect()->route('admin.dashboard.index');

        }


        Flasher::addError(__('msg_your_license_invalid'), __('msg_error'));

        return redirect()->back();
    }
}
