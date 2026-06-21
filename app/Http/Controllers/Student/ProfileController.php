<?php

namespace App\Http\Controllers\Student;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Student;

class ProfileController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct ()
    {
        // Module Data
        $this->title     = trans_choice('module_profile', 1);
        $this->route     = 'student.profile';
        $this->view      = 'student.profile';
        $this->path      = 'student';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $data['row'] = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;


        if($id == Auth::guard('student')->user()->id){

            $data['row'] = Student::findOrFail($id);
        }

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'gender' => 'required',
        ]);


        if($id == Auth::guard('student')->user()->id){

            // Update data
            $student = Student::find($id);
            $student->gender = $request->gender;
            $student->marital_status = $request->marital_status;
            $student->blood_group = $request->blood_group;
            $student->save();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        }
        else {

            Flasher::addError(__('msg_not_permitted'), __('msg_error'));
        }

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function account()
    {
        //
        $data['title']  = trans_choice('module_admin_setting', 1);
        $data['route']  = $this->route;
        $data['view']   = $this->view;

        return view($this->view.'.account', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changeMail(Request $request)
    {
        // Field Validation
        $request->validate([
            'email' => 'required|email|unique:students,email',
        ]);

        // Check
        if($request->email != Auth::guard('student')->user()->email){

            $user = Student::find(Auth::guard('student')->user()->id);
            $user->email = $request->email;
            $user->save();

            // Logout
            Auth::guard('student')->logout();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->route('student.login');
        }
        else{

            Flasher::addError(__('msg_email_invalid'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePass(Request $request)
    {
        // Field Validation
        $request->validate([
            'old_password' => 'required',
            'password' => 'nullable|confirmed|min:8',
        ]);

        $oldPassword = $request->old_password;
        $hashedPassword = Auth::guard('student')->user()->password;

        // Check old password for validation
        if(Hash::check($oldPassword, $hashedPassword)){

            $user = Student::find(Auth::guard('student')->user()->id);
            $user->password = Hash::make($request->password);
            $user->password_text = Crypt::encryptString($request->password);
            $user->save();

            // Logout
            Auth::guard('student')->logout();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->route('student.login');
        }
        else{

            Flasher::addError(__('msg_password_invalid'), __('msg_error'));

            return redirect()->back();
        }
    }
}
