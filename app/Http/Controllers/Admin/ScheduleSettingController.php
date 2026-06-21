<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use App\Models\ScheduleSetting;
use Illuminate\Http\Request;

class ScheduleSettingController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_schedule_setting', 1);
        $this->route = 'admin.schedule-setting';
        $this->view = 'admin.schedule-setting';
        $this->access = 'schedule-setting';


        $this->middleware('permission:'.$this->access.'-view');
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

        $data['row'] = ScheduleSetting::where('slug', 'fees-schedule')->first();

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
            'day' => 'required|integer',
            'time' => 'required',
        ]);



        $id = $request->id;

        // -1 means no data row found
        if($id == -1){
            // Insert Data
            $data = new ScheduleSetting;
            $data->slug = $request->slug;
            $data->day = $request->day;
            $data->time = $request->time;
            $data->email = $request->email ?? '0';
            $data->sms = $request->sms ?? '0';
            $data->save();
        }
        else{
            // Update Data
            $data = ScheduleSetting::find($id);
            $data->slug = $request->slug;
            $data->day = $request->day;
            $data->time = $request->time;
            $data->email = $request->email ?? '0';
            $data->sms = $request->sms ?? '0';
            $data->save();
        }


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
