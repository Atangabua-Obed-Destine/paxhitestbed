<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdCardSetting;
use App\Traits\FileUploader;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/**
 * The artwork behind the staff ID card.
 *
 * Deliberately narrower than StudentIdCardSettingController: the staff card
 * draws nothing else from its settings row — the wording and crest are part of
 * the artwork, and the name, photo and staff id come from the staff record — so
 * offering title/subtitle/address/validity here would be four fields that are
 * saved and then ignored. Both cards share the id_card_settings table, kept
 * apart by slug.
 */
class StaffIdCardSettingController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access, $slug;

    public function __construct()
    {
        $this->title = trans_choice('module_staff', 1) . ' ' . __('field_id_card') . ' ' . __('Setting');
        $this->route = 'admin.staff-id-card-setting';
        $this->view = 'admin.staff-id-card-setting';
        // Same folder as the student artwork; uploads are timestamped, so they
        // cannot collide.
        $this->path = 'card-setting';
        $this->access = 'staff-id-card-setting';
        $this->slug = 'staff-id-card';

        $this->middleware('permission:' . $this->access . '-view')->only('index');
        $this->middleware('permission:' . $this->access . '-edit')->only('store');
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;
        $data['row'] = IdCardSetting::where('slug', $this->slug)->first();

        return view($this->view . '.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'background' => 'nullable|image',
        ]);

        // firstOrNew rather than find($request->id): the row is keyed by slug,
        // and trusting a posted id would let one card's artwork be written over
        // the other's.
        $setting = IdCardSetting::firstOrNew(['slug' => $this->slug]);

        if (!$setting->exists) {
            $setting->title = trans_choice('module_staff', 1) . ' ' . __('field_id_card');
            $setting->status = 1;
        }

        $setting->background = $this->updateMultiMedia($request, 'background', $this->path, $setting, 'background');
        $setting->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
