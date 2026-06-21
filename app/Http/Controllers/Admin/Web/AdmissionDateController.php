<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\AdmissionDate;

class AdmissionDateController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title = trans_choice('module_admission_date', 1);
        $this->route = 'admin.admission-date';
        $this->view = 'admin.admission-date';
        $this->access = 'admission-date';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;
        $data['rows'] = AdmissionDate::orderBy('event_date', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'event_title' => 'required|max:191',
            'event_date' => 'required|date',
        ]);

        $admissionDate = new AdmissionDate;
        $admissionDate->event_title = $request->event_title;
        $admissionDate->event_date = $request->event_date;
        $admissionDate->description = $request->description;
        $admissionDate->sort_order = $request->sort_order ?? 0;
        $admissionDate->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function update(Request $request, AdmissionDate $admissionDate)
    {
        $request->validate([
            'event_title' => 'required|max:191',
            'event_date' => 'required|date',
        ]);

        $admissionDate->event_title = $request->event_title;
        $admissionDate->event_date = $request->event_date;
        $admissionDate->description = $request->description;
        $admissionDate->sort_order = $request->sort_order ?? 0;
        $admissionDate->status = $request->status;
        $admissionDate->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function destroy(AdmissionDate $admissionDate)
    {
        $admissionDate->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));
        return redirect()->back();
    }
}
