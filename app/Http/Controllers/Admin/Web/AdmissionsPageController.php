<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\AdmissionsPage;
use App\Models\Language;

class AdmissionsPageController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = 'Admissions Page';
        $this->route    = 'admin.admissions-page';
        $this->view     = 'admin.web.admissions-page';
        $this->path     = 'admissions-page';
        $this->access   = 'admissions-page';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-edit', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = AdmissionsPage::orderBy('id', 'desc')->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;

        $data['row'] = AdmissionsPage::findOrFail($id);
        $data['languages'] = Language::where('status', '1')->orderBy('name', 'asc')->get();

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'language_id' => 'required',
            'title' => 'required|max:191',
            'subtitle' => 'nullable|max:191',
            'description' => 'nullable',
            'requirements' => 'nullable',
            'contact_info' => 'nullable',
            'banner_image' => 'nullable|image',
            'status' => 'required',
        ]);

        // Update Data
        $admissionsPage = AdmissionsPage::findOrFail($id);
        $admissionsPage->language_id = $request->language_id;
        $admissionsPage->title = $request->title;
        $admissionsPage->subtitle = $request->subtitle;
        $admissionsPage->description = $request->description;
        $admissionsPage->requirements = $request->requirements;
        $admissionsPage->contact_info = $request->contact_info;
        $admissionsPage->meta_title = $request->meta_title;
        $admissionsPage->meta_description = $request->meta_description;
        $admissionsPage->meta_keywords = $request->meta_keywords;
        $admissionsPage->status = $request->status;

        // Handle process steps
        if ($request->filled('step_titles')) {
            $steps = [];
            foreach ($request->step_titles as $index => $title) {
                if (!empty($title)) {
                    $steps[] = [
                        'title' => $title,
                        'description' => $request->step_descriptions[$index] ?? '',
                    ];
                }
            }
            $admissionsPage->process_steps = $steps;
        }

        // Handle banner image upload
        if ($request->hasFile('banner_image')) {
            $admissionsPage->banner_image = $this->updateMedia($request, 'banner_image', $this->path, $admissionsPage->banner_image, 1200, 400);
        }

        $admissionsPage->save();

        Flasher::addSuccess('Data has been updated successfully.');

        return redirect()->route($this->route.'.index');
    }
}
