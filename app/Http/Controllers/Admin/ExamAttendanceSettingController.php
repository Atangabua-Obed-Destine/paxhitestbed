<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExamAttendanceSetting;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;

class ExamAttendanceSettingController extends Controller
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
        $this->title = 'Exam Attendance Eligibility';
        $this->route = 'admin.exam-attendance-settings';
        $this->view = 'admin.exam-attendance-settings';
        $this->path = 'exam-attendance-settings';
        $this->access = 'exam';


        $this->middleware('permission:'.$this->access.'-attendance', ['only' => ['index','update']]);
    }

    /**
     * Display the settings form.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get or create settings
        $data['setting'] = ExamAttendanceSetting::firstOrCreate(
            ['id' => 1],
            [
                'minimum_attendance_percentage' => 70.00,
                'is_enabled' => true,
                'updated_by' => Auth::guard('web')->user()->id,
            ]
        );

        return view($this->view.'.index', $data);
    }

    /**
     * Update the settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // Field Validation
        $request->validate([
            'minimum_attendance_percentage' => 'required|numeric|min:0|max:100',
            'is_enabled' => 'nullable|boolean',
        ]);

        $setting = ExamAttendanceSetting::firstOrFail();
        
        $setting->minimum_attendance_percentage = $request->minimum_attendance_percentage;
        $setting->is_enabled = $request->has('is_enabled') ? 1 : 0;
        $setting->updated_by = Auth::guard('web')->user()->id;
        $setting->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
