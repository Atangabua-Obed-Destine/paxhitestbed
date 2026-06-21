<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormA3Setting;
use Illuminate\Http\Request;
use Toastr;

class FormA3SettingController extends Controller
{
    public function index()
    {
        $data['title'] = 'Form A3 Configuration';
        $data['row'] = FormA3Setting::first();
        return view('admin.form-a3-setting.index', $data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'hnd_coordinator_name' => 'required|string|max:255',
            'dir_acad_name' => 'required|string|max:255',
        ]);

        $setting = FormA3Setting::first();
        if (!$setting) {
            $setting = new FormA3Setting();
        }

        $setting->hnd_coordinator_name = $request->hnd_coordinator_name;
        $setting->dir_acad_name = $request->dir_acad_name;
        $setting->save();

        Toastr::success(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
