<?php

if (!function_exists('upload_asset')) {
    /**
     * Get the asset URL for an uploaded file in public/uploads directory.
     * 
     * @param string $path The relative path within uploads (e.g., 'student/photo.jpg' or 'welcome-message/image.png')
     * @return string|null Returns asset URL if file exists, null otherwise
     */
    function upload_asset($path)
    {
        if (empty($path)) {
            return null;
        }

        // Remove leading slash if present
        $path = ltrim($path, '/');

        // Check if file exists in public/uploads
        if (file_exists(public_path('uploads/' . $path))) {
            return asset('uploads/' . $path);
        }

        return null;
    }
}

if (!function_exists('upload_exists')) {
    /**
     * Check if an uploaded file exists in public/uploads directory.
     * 
     * @param string $path The relative path within uploads (e.g., 'student/photo.jpg')
     * @return bool Returns true if file exists
     */
    function upload_exists($path)
    {
        if (empty($path)) {
            return false;
        }

        // Remove leading slash if present
        $path = ltrim($path, '/');

        // Check if file exists in public/uploads
        return file_exists(public_path('uploads/' . $path));
    }
}

if (!function_exists('institution_name')) {
    /**
     * What this institution is called.
     *
     * Views used to write `$setting->title ?? 'PAX HIGHER INSTITUTE'`, which put
     * one particular school's name into the code of a system meant to serve any
     * — and left forty places to edit when it changed. There is now one answer.
     */
    function institution_name(): string
    {
        return app(\App\Services\LetterheadService::class)->institutionName();
    }
}

if (!function_exists('institution_code')) {
    /** The short form, for places too narrow for the full name. */
    function institution_code(): string
    {
        return app(\App\Services\LetterheadService::class)->institutionCode();
    }
}
