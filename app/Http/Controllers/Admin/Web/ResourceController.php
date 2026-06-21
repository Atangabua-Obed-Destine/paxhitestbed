<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Web\Resource;
use App\Models\Language;
use Illuminate\Support\Facades\File;

class ResourceController extends Controller
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
        $this->title   = 'Resources & Downloads';
        $this->route   = 'admin.resource';
        $this->view    = 'admin.web.resource';
        $this->path    = 'resources';
        $this->access  = 'resource';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;
        $data['access'] = $this->access;
        
        $data['categories'] = Resource::getCategoryOptions();

        $query = Resource::where('language_id', Language::version()->id);
        
        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $data['rows'] = $query->orderby('sort_order', 'asc')
                        ->orderby('id', 'desc')
                        ->get();
        
        $data['selected_category'] = $request->category ?? '';

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['access'] = $this->access;
        $data['categories'] = Resource::getCategoryOptions();

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:255',
            'category' => 'required|string',
            'file' => 'required|file|max:51200', // Max 50MB
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Handle file upload
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();
        
        // Generate unique filename
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $filename = str_replace([' ','-','&','#','$','%','^',';',':'], '_', $filename);
        $fileNameToStore = $filename . '_' . time() . '.' . $extension;
        
        // Create directory if not exists
        $path = public_path('uploads/' . $this->path . '/');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0777, true, true);
        }
        
        // Move file
        $file->move($path, $fileNameToStore);

        // Data Insert
        $resource = new Resource;
        $resource->language_id = Language::version()->id;
        $resource->title = $request->title;
        $resource->description = $request->description;
        $resource->category = $request->category;
        $resource->icon = $request->icon;
        $resource->file_path = $fileNameToStore;
        $resource->file_name = $originalName;
        $resource->file_size = $fileSize;
        $resource->file_type = $mimeType;
        $resource->sort_order = $request->sort_order ?? 0;
        $resource->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Resource $resource)
    {
        // Redirect to edit
        return redirect()->route($this->route.'.edit', $resource);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Resource $resource)
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['access'] = $this->access;
        $data['categories'] = Resource::getCategoryOptions();
        $data['row'] = $resource;

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Resource $resource)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:255',
            'category' => 'required|string',
            'file' => 'nullable|file|max:51200', // Max 50MB
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Handle file upload if new file provided
        if ($request->hasFile('file')) {
            // Delete old file
            $oldFile = public_path('uploads/' . $this->path . '/' . $resource->file_path);
            if (File::isFile($oldFile)) {
                File::delete($oldFile);
            }
            
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            
            // Generate unique filename
            $filename = pathinfo($originalName, PATHINFO_FILENAME);
            $filename = str_replace([' ','-','&','#','$','%','^',';',':'], '_', $filename);
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            
            // Create directory if not exists
            $path = public_path('uploads/' . $this->path . '/');
            if (!File::exists($path)) {
                File::makeDirectory($path, 0777, true, true);
            }
            
            // Move file
            $file->move($path, $fileNameToStore);
            
            $resource->file_path = $fileNameToStore;
            $resource->file_name = $originalName;
            $resource->file_size = $fileSize;
            $resource->file_type = $mimeType;
        }

        // Data Update
        $resource->title = $request->title;
        $resource->description = $request->description;
        $resource->category = $request->category;
        $resource->icon = $request->icon;
        $resource->sort_order = $request->sort_order ?? 0;
        $resource->status = $request->status ?? 1;
        $resource->update();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Resource $resource)
    {
        // Delete file
        $filePath = public_path('uploads/' . $this->path . '/' . $resource->file_path);
        if (File::isFile($filePath)) {
            File::delete($filePath);
        }

        // Delete Data
        $resource->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Track download and serve file.
     */
    public function download(Resource $resource)
    {
        // Increment download count
        $resource->increment('download_count');
        
        $filePath = public_path('uploads/' . $this->path . '/' . $resource->file_path);
        
        if (!File::exists($filePath)) {
            abort(404, 'File not found.');
        }
        
        return response()->download($filePath, $resource->file_name);
    }
}
