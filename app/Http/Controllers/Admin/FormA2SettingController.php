<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormA2Setting;
use Illuminate\Http\Request;
use Toastr;

class FormA2SettingController extends Controller
{
    public function index()
    {
        $data['title'] = 'Form A2 Configuration';
        $data['row'] = FormA2Setting::first();
        return view('admin.form-a2-setting.index', $data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'finance_director_name' => 'required|string|max:255',
            'registrar_name' => 'required|string|max:255',
        ]);

        $setting = FormA2Setting::first();
        if (!$setting) {
            $setting = new FormA2Setting();
        }

        $setting->finance_director_name = $request->finance_director_name;
        $setting->registrar_name = $request->registrar_name;
        $setting->save();

        Toastr::success(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
