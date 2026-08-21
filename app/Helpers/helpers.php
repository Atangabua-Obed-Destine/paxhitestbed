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

if (!function_exists('site_subtitle')) {
    /**
     * The line that sits under the institution's name — its founding body,
     * diocese, motto, or whatever the school chooses.
     *
     * Configured under Settings → Site Subtitle. It was previously written into
     * the views as "Archdiocese of Bamenda", which is true of exactly one
     * institution and wrong for every other deployment of this system.
     *
     * Returns an empty string when unset, so callers can leave the line out
     * rather than print a stray label with nothing after it.
     */
    function site_subtitle(): string
    {
        return trim((string) optional(\App\Models\Setting::first())->site_subtitle);
    }
}

if (!function_exists('avatar_url')) {
    /**
     * A portrait URL that always points at something that exists.
     *
     * Views used to build `asset('uploads/user/' . $photo)` unconditionally.
     * When the column is null that resolves to the directory itself — the server
     * answers 403 — and when it names a file that is no longer on disk, 404.
     * Both fire a failed request on every page load, and an onerror fallback
     * hides the picture without preventing the request.
     *
     * @param  string|null $photo     the stored filename, if any
     * @param  string      $directory where that filename lives under uploads/
     * @param  string|null $fallback  asset path to use instead; a neutral
     *                                placeholder when not given
     */
    function avatar_url(?string $photo, string $directory = 'user', ?string $fallback = null): string
    {
        $fallback = $fallback ?: 'dashboard/images/user.jpg';
        $photo = trim((string) $photo);

        if ($photo !== '' && is_file(public_path('uploads/' . $directory . '/' . $photo))) {
            return asset('uploads/' . $directory . '/' . $photo);
        }

        return asset($fallback);
    }
}
