<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\SupportService;

class SupportServiceController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title = trans_choice('module_support_service', 1);
        $this->route = 'admin.support-service';
        $this->view = 'admin.support-service';
        $this->access = 'support-service';

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
        $data['rows'] = SupportService::orderBy('sort_order', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:191',
            'description' => 'required',
        ]);

        $service = new SupportService;
        $service->title = $request->title;
        $service->description = $request->description;
        $service->icon = $request->icon;
        $service->contact_info = $request->contact_info;
        $service->sort_order = $request->sort_order ?? 0;
        $service->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function update(Request $request, SupportService $supportService)
    {
        $request->validate([
            'title' => 'required|max:191',
            'description' => 'required',
        ]);

        $supportService->title = $request->title;
        $supportService->description = $request->description;
        $supportService->icon = $request->icon;
        $supportService->contact_info = $request->contact_info;
        $supportService->sort_order = $request->sort_order ?? 0;
        $supportService->status = $request->status;
        $supportService->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function destroy(SupportService $supportService)
    {
        $supportService->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));
        return redirect()->back();
    }
}
