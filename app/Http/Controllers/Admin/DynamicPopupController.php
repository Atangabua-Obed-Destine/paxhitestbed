<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicPopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Flasher\Laravel\Facade\Flasher;
use Intervention\Image\Facades\Image;

class DynamicPopupController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = __('Dynamic Popups');
        $this->route = 'admin.marketing.dynamic-popup';
        $this->view = 'admin.dynamic-popup';
        $this->path = 'dynamic-popups';
        $this->access = 'dynamic-popup';

        $this->middleware('permission:' . $this->access . '-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:' . $this->access . '-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:' . $this->access . '-edit', ['only' => ['edit', 'update', 'toggleStatus']]);
        $this->middleware('permission:' . $this->access . '-delete', ['only' => ['destroy', 'bulkDelete']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $query = DynamicPopup::with('creator')->orderBy('priority', 'desc')->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('summary', 'like', '%' . $search . '%');
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Target area filter
        if ($request->filled('target_area')) {
            $query->where(function ($q) use ($request) {
                $q->whereJsonContains('target_areas', $request->target_area)
                  ->orWhereJsonContains('target_areas', DynamicPopup::AREA_ALL);
            });
        }

        $data['rows'] = $query->paginate(15);
        $data['targetAreas'] = DynamicPopup::getTargetAreas();

        return view($this->view . '.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['targetAreas'] = DynamicPopup::getTargetAreas();
        $data['displayFrequencies'] = DynamicPopup::getDisplayFrequencies();
        $data['popupPositions'] = DynamicPopup::getPopupPositions();

        return view($this->view . '.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'summary' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'button_text' => 'nullable|string|max:50',
            'button_color' => 'required|string|max:10',
            'button_text_color' => 'required|in:light,dark',
            'link' => 'nullable|url|max:500',
            'target_areas' => 'required|array|min:1',
            'target_areas.*' => 'in:' . implode(',', array_keys(DynamicPopup::getTargetAreas())),
            'display_frequency' => 'required|in:' . implode(',', array_keys(DynamicPopup::getDisplayFrequencies())),
            'popup_position' => 'required|in:' . implode(',', array_keys(DynamicPopup::getPopupPositions())),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'required|integer|min:0|max:100',
            'is_dismissible' => 'boolean',
            'status' => 'boolean',
        ]);

        try {
            $popup = new DynamicPopup();
            $popup->title = $request->title;
            $popup->summary = $request->summary;
            $popup->button_text = $request->button_text;
            $popup->button_color = $request->button_color;
            $popup->button_text_color = $request->button_text_color;
            $popup->link = $request->link;
            $popup->target_areas = $request->target_areas;
            $popup->display_frequency = $request->display_frequency;
            $popup->popup_position = $request->popup_position;
            $popup->start_date = $request->start_date;
            $popup->end_date = $request->end_date;
            $popup->priority = $request->priority;
            $popup->is_dismissible = $request->boolean('is_dismissible', true);
            $popup->status = $request->boolean('status', true);
            $popup->created_by = Auth::guard('web')->id();
            $popup->updated_by = Auth::guard('web')->id();

            // Handle image upload
            if ($request->hasFile('image')) {
                $popup->image = $this->uploadImage($request->file('image'));
            }

            $popup->save();

            Flasher::addSuccess(__('Dynamic popup created successfully.'));
            return redirect()->route($this->route . '.index');

        } catch (\Exception $e) {
            Flasher::addError(__('Failed to create popup: ') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $dynamicPopup = DynamicPopup::findOrFail($id);
        
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['row'] = $dynamicPopup;
        $data['targetAreas'] = DynamicPopup::getTargetAreas();
        $data['displayFrequencies'] = DynamicPopup::getDisplayFrequencies();
        $data['popupPositions'] = DynamicPopup::getPopupPositions();

        return view($this->view . '.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $dynamicPopup = DynamicPopup::findOrFail($id);
        
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['row'] = $dynamicPopup;

        $data['targetAreas'] = DynamicPopup::getTargetAreas();
        $data['displayFrequencies'] = DynamicPopup::getDisplayFrequencies();
        $data['popupPositions'] = DynamicPopup::getPopupPositions();

        return view($this->view . '.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $dynamicPopup = DynamicPopup::findOrFail($id);
        
        $request->validate([
            'title' => 'required|string|max:100',
            'summary' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'button_text' => 'nullable|string|max:50',
            'button_color' => 'required|string|max:10',
            'button_text_color' => 'required|in:light,dark',
            'link' => 'nullable|url|max:500',
            'target_areas' => 'required|array|min:1',
            'target_areas.*' => 'in:' . implode(',', array_keys(DynamicPopup::getTargetAreas())),
            'display_frequency' => 'required|in:' . implode(',', array_keys(DynamicPopup::getDisplayFrequencies())),
            'popup_position' => 'required|in:' . implode(',', array_keys(DynamicPopup::getPopupPositions())),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'priority' => 'required|integer|min:0|max:100',
            'is_dismissible' => 'boolean',
            'status' => 'boolean',
        ]);

        try {
            $dynamicPopup->title = $request->title;
            $dynamicPopup->summary = $request->summary;
            $dynamicPopup->button_text = $request->button_text;
            $dynamicPopup->button_color = $request->button_color;
            $dynamicPopup->button_text_color = $request->button_text_color;
            $dynamicPopup->link = $request->link;
            $dynamicPopup->target_areas = $request->target_areas;
            $dynamicPopup->display_frequency = $request->display_frequency;
            $dynamicPopup->popup_position = $request->popup_position;
            $dynamicPopup->start_date = $request->start_date;
            $dynamicPopup->end_date = $request->end_date;
            $dynamicPopup->priority = $request->priority;
            $dynamicPopup->is_dismissible = $request->boolean('is_dismissible', true);
            $dynamicPopup->status = $request->boolean('status', true);
            $dynamicPopup->updated_by = Auth::guard('web')->id();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                $this->deleteImage($dynamicPopup->image);
                $dynamicPopup->image = $this->uploadImage($request->file('image'));
            }

            // Handle image removal
            if ($request->boolean('remove_image') && $dynamicPopup->image) {
                $this->deleteImage($dynamicPopup->image);
                $dynamicPopup->image = null;
            }

            $dynamicPopup->save();

            Flasher::addSuccess(__('Dynamic popup updated successfully.'));
            return redirect()->route($this->route . '.index');

        } catch (\Exception $e) {
            Flasher::addError(__('Failed to update popup: ') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $dynamicPopup = DynamicPopup::findOrFail($id);
        
        try {
            if ($dynamicPopup->is_pinned) {
                Flasher::addWarning(__('Cannot delete pinned popup. Unpin it first.'));
                return redirect()->back();
            }

            // Delete image
            $this->deleteImage($dynamicPopup->image);

            $dynamicPopup->delete();

            Flasher::addSuccess(__('Dynamic popup deleted successfully.'));

        } catch (\Exception $e) {
            Flasher::addError(__('Failed to delete popup: ') . $e->getMessage());
        }

        return redirect()->route($this->route . '.index');
    }

    /**
     * Toggle popup status
     */
    public function toggleStatus(Request $request, $id)
    {
        $dynamicPopup = DynamicPopup::findOrFail($id);
        
        try {
            $dynamicPopup->status = !$dynamicPopup->status;
            $dynamicPopup->updated_by = Auth::guard('web')->id();
            $dynamicPopup->save();

            return response()->json([
                'success' => true,
                'status' => $dynamicPopup->status,
                'message' => $dynamicPopup->status ? __('Popup activated') : __('Popup deactivated'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle pinned status
     */
    public function togglePinned(Request $request, $id)
    {
        $dynamicPopup = DynamicPopup::findOrFail($id);
        
        try {
            $dynamicPopup->is_pinned = !$dynamicPopup->is_pinned;
            $dynamicPopup->updated_by = Auth::guard('web')->id();
            $dynamicPopup->save();

            return response()->json([
                'success' => true,
                'is_pinned' => $dynamicPopup->is_pinned,
                'message' => $dynamicPopup->is_pinned ? __('Popup pinned') : __('Popup unpinned'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete popups
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:dynamic_popups,id',
        ]);

        try {
            $popups = DynamicPopup::whereIn('id', $request->ids)
                ->where('is_pinned', false)
                ->get();

            $deleted = 0;
            foreach ($popups as $popup) {
                $this->deleteImage($popup->image);
                $popup->delete();
                $deleted++;
            }

            $skipped = count($request->ids) - $deleted;
            $message = __(':count popup(s) deleted.', ['count' => $deleted]);
            if ($skipped > 0) {
                $message .= ' ' . __(':count pinned popup(s) skipped.', ['count' => $skipped]);
            }

            Flasher::addSuccess($message);

        } catch (\Exception $e) {
            Flasher::addError(__('Failed to delete popups: ') . $e->getMessage());
        }

        return redirect()->route($this->route . '.index');
    }

    /**
     * Get popups for display (API endpoint)
     */
    public function getPopupsForArea(Request $request)
    {
        $area = $request->input('area', DynamicPopup::AREA_FRONT_WEB);
        
        $popups = DynamicPopup::getPopupsForArea($area);

        return response()->json([
            'success' => true,
            'popups' => $popups->map(function ($popup) {
                return [
                    'id' => $popup->id,
                    'title' => $popup->title,
                    'summary' => $popup->summary,
                    'image' => $popup->image_url,
                    'button_text' => $popup->button_text,
                    'button_color' => $popup->button_color,
                    'button_text_color' => $popup->button_text_color,
                    'link' => $popup->link,
                    'display_frequency' => $popup->display_frequency,
                    'popup_position' => $popup->popup_position,
                    'is_dismissible' => $popup->is_dismissible,
                    'priority' => $popup->priority,
                ];
            }),
        ]);
    }

    /**
     * Mark a popup as dismissed (for tracking purposes, uses session)
     */
    public function dismissPopup(Request $request, $id)
    {
        $popup = DynamicPopup::find($id);
        
        if (!$popup) {
            return response()->json(['success' => false, 'message' => 'Popup not found'], 404);
        }

        // Store dismissed popup ID in session
        $dismissed = session()->get('dismissed_popups', []);
        if (!in_array($id, $dismissed)) {
            $dismissed[] = $id;
            session()->put('dismissed_popups', $dismissed);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Upload image helper
     */
    protected function uploadImage($file): string
    {
        $uploadPath = public_path('uploads/' . $this->path);
        
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $filename = Str::random(20) . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Resize image to 512x280
        $image = Image::make($file);
        $image->fit(512, 280, function ($constraint) {
            $constraint->upsize();
        });
        $image->save($uploadPath . '/' . $filename, 90);

        return $filename;
    }

    /**
     * Delete image helper
     */
    protected function deleteImage(?string $filename): void
    {
        if ($filename) {
            $path = public_path('uploads/' . $this->path . '/' . $filename);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
