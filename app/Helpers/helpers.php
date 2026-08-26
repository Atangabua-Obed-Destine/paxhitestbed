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

if (!function_exists('admin_rich_text')) {
    /**
     * Render text an administrator typed into a plain textarea as decent HTML.
     *
     * Fields like a degree type's payment instructions are captured in a bare
     * <textarea> and were being echoed straight into the page, so an admin who
     * pressed Enter between two instructions got one run-on paragraph, and a
     * list typed as "- do this" stayed literal dashes. This keeps the raw
     * escape hatch for an admin who genuinely pastes markup, and otherwise
     * turns what they typed into paragraphs and lists.
     *
     *  - already contains markup  -> returned untouched (trusted admin input)
     *  - lines starting - or *    -> unordered list
     *  - lines starting 1. 2)     -> ordered list
     *  - anything else            -> one paragraph per block, escaped
     */
    function admin_rich_text(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        // An admin who pasted real HTML gets it back as-is.
        if (preg_match('/<(p|ul|ol|li|br|div|strong|em|b|i|a|h[1-6]|table)\b/i', $value)) {
            return $value;
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);
        $lines = array_values(array_filter(array_map('trim', $lines), function ($l) {
            return $l !== '';
        }));

        if ($lines === []) {
            return '';
        }

        $bullet  = 0;
        $ordered = 0;
        foreach ($lines as $line) {
            if (preg_match('/^[-*\x{2022}]\s+/u', $line)) {
                $bullet++;
            } elseif (preg_match('/^\d+[.)]\s+/', $line)) {
                $ordered++;
            }
        }

        $half = max(1, (int) ceil(count($lines) / 2));

        if ($ordered >= $half) {
            $html = '<ol>';
            foreach ($lines as $line) {
                $html .= '<li>' . e(preg_replace('/^\d+[.)]\s+/', '', $line)) . '</li>';
            }
            return $html . '</ol>';
        }

        if ($bullet >= $half) {
            $html = '<ul>';
            foreach ($lines as $line) {
                $html .= '<li>' . e(preg_replace('/^[-*\x{2022}]\s+/u', '', $line)) . '</li>';
            }
            return $html . '</ul>';
        }

        $html = '';
        foreach ($lines as $line) {
            $html .= '<p>' . e($line) . '</p>';
        }

        return $html;
    }
}
