<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Desk;
use App\Models\Equipment\Setting;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/** Desk settings (shared with the library's scan desk) and the list of lending desks. */
class EquipmentSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:equipment-manage');
    }

    public function edit()
    {
        return view('admin.equipment.settings', [
            'title' => __('Desk settings'),
            'deskSetting' => Setting::current(),
            'desks' => Desk::withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_due_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'first_reminder_minutes' => 'required|integer|min:0|max:1440',
            'daily_reminder_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'library_loan_days' => 'required|integer|min:1|max:365',
        ]);
        $data['block_across_desks'] = $request->boolean('block_across_desks');
        Setting::current()->update($data);

        Flasher::addSuccess(__('Desk settings saved.'), __('msg_success'));

        return back();
    }

    public function storeDesk(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100|unique:equipment_desks,name']);
        Desk::create(['name' => trim($data['name']), 'status' => true]);
        Flasher::addSuccess(__('Desk added.'), __('msg_success'));

        return back();
    }

    public function updateDesk(Request $request, Desk $desk)
    {
        $data = $request->validate(['name' => 'required|string|max:100|unique:equipment_desks,name,' . $desk->id]);
        $desk->update(['name' => trim($data['name']), 'status' => $request->boolean('status')]);
        Flasher::addSuccess(__('Desk saved.'), __('msg_success'));

        return back();
    }
}
