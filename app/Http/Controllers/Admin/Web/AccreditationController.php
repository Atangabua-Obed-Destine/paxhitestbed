<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Accreditation;

class AccreditationController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = trans_choice('module_accreditation', 1);
        $this->route = 'admin.accreditation';
        $this->view = 'admin.accreditation';
        $this->path = 'accreditation';
        $this->access = 'accreditation';

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
        $data['path'] = $this->path;
        $data['access'] = $this->access;
        $data['rows'] = Accreditation::orderBy('sort_order', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:191',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $accreditation = new Accreditation;
        $accreditation->title = $request->title;
        $accreditation->description = $request->description;
        $accreditation->url = $request->url;
        $accreditation->sort_order = $request->sort_order ?? 0;
        $accreditation->logo = $this->uploadMedia($request, 'logo', $this->path);
        $accreditation->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function update(Request $request, Accreditation $accreditation)
    {
        $request->validate([
            'title' => 'required|max:191',
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $accreditation->title = $request->title;
        $accreditation->description = $request->description;
        $accreditation->url = $request->url;
        $accreditation->sort_order = $request->sort_order ?? 0;
        $accreditation->status = $request->status;
        $accreditation->logo = $this->updateMedia($request, 'logo', $this->path, $accreditation);
        $accreditation->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function destroy(Accreditation $accreditation)
    {
        $this->deleteMedia($this->path, $accreditation);
        $accreditation->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));
        return redirect()->back();
    }
}
