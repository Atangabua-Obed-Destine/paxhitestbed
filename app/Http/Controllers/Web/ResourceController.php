<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\Resource;
use App\Models\Language;
use Illuminate\Support\Facades\File;

class ResourceController extends Controller
{
    /**
     * Display a listing of the resources.
     */
    public function index()
    {
        // Get all active resources grouped by category
        $resources = Resource::where('language_id', Language::version()->id)
                            ->where('status', 1)
                            ->orderBy('category', 'asc')
                            ->orderBy('sort_order', 'asc')
                            ->orderBy('title', 'asc')
                            ->get();
        
        // Group by category
        $data['grouped_resources'] = $resources->groupBy('category');
        $data['categories'] = Resource::getCategoryOptions();
        
        // Get featured resources (student guide and calendarium)
        $data['featured_resources'] = Resource::where('language_id', Language::version()->id)
                            ->where('status', 1)
                            ->whereIn('category', ['student_guide', 'calendarium'])
                            ->orderBy('sort_order', 'asc')
                            ->get();

        return view('web2.resources', $data);
    }

    /**
     * Track download and serve file.
     */
    public function download(Resource $resource)
    {
        // Only allow downloading active resources
        if (!$resource->status) {
            abort(404, 'Resource not available.');
        }
        
        // Increment download count
        $resource->increment('download_count');
        
        $filePath = public_path('uploads/resources/' . $resource->file_path);
        
        if (!File::exists($filePath)) {
            abort(404, 'File not found.');
        }
        
        return response()->download($filePath, $resource->file_name);
    }
}
