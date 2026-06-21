<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditorImageUploadController extends Controller
{
    /**
     * Handle image upload from TinyMCE editor (paste or drag-drop).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,gif,webp,bmp|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('file');
            
            // Generate unique filename
            $filename = 'editor_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            
            // Define upload path
            $uploadPath = 'uploads/editor';
            
            // Ensure directory exists
            $fullPath = public_path($uploadPath);
            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            
            // Move file to public uploads folder
            $file->move($fullPath, $filename);
            
            // Return the URL for TinyMCE
            $url = asset($uploadPath . '/' . $filename);
            
            return response()->json([
                'location' => $url
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
