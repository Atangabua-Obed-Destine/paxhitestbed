<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Response;

/**
 * Making the student portal installable.
 *
 * All three of these are served by routes rather than as static files, because
 * the manifest's scope and start_url have to come from APP_URL - this app runs
 * under a sub-path locally and at the root in production, and a manifest whose
 * start_url falls outside its own scope silently refuses to install.
 *
 * The worker is deliberately served from student/sw.js. A service worker's scope
 * is its own directory, so this one CANNOT intercept an admin or applicant
 * request. That safety comes from the URL itself rather than from a rule someone
 * has to remember, which is the only kind of safety worth having here: an admin
 * screen served from a stale cache would be far worse than no PWA at all.
 */
class PwaController extends Controller
{
    /**
     * The manifest, built from the school's own settings.
     */
    public function manifest()
    {
        $setting = Setting::where('status', '1')->first();
        $name = $setting->title ?? config('app.name');

        $manifest = [
            'id' => $this->scope(),
            'name' => $name,
            'short_name' => config('pwa.short_name') ?: $this->shorten($name),
            'description' => __('The student portal for :school', ['school' => $name]),
            'start_url' => route('student.login'),
            'scope' => $this->scope(),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => config('pwa.theme_color'),
            'background_color' => config('pwa.background_color'),
            'icons' => $this->icons(),
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * The service worker.
     *
     * Served as a view so the cache version and the URLs it must never touch are
     * resolved here rather than hardcoded in a static file that cannot know the
     * app's base path.
     */
    public function serviceWorker(): Response
    {
        $body = view('student.pwa.sw', [
            'enabled' => (bool) config('pwa.enabled'),
            'version' => (string) config('pwa.version'),
            'scope' => $this->scope(),
            'offlineUrl' => route('student.pwa.offline'),
            'precache' => array_values(array_filter([
                route('student.pwa.offline'),
                asset('pwa/icon-192.png'),
                asset('pwa/icon-512.png'),
            ])),
        ])->render();

        return response($body, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // Never let a proxy or the browser hold on to the worker itself, or
            // the kill switch could not reach a phone that already has it.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            // Belt and braces: the scope is already the directory this is served
            // from, but saying so makes the intent explicit.
            'Service-Worker-Allowed' => $this->scopePath(),
        ]);
    }

    /**
     * What a student sees with no connection.
     *
     * The only page the worker keeps, so it carries nothing personal - just the
     * school, how to reach them, and what needs a connection.
     */
    public function offline()
    {
        return view('student.pwa.offline', [
            'setting' => Setting::where('status', '1')->first(),
        ]);
    }

    /**
     * The absolute scope: everything under the student prefix, and nothing else.
     */
    protected function scope(): string
    {
        return rtrim(url('student'), '/') . '/';
    }

    /**
     * The same, as a path, for the Service-Worker-Allowed header.
     */
    protected function scopePath(): string
    {
        $path = parse_url($this->scope(), PHP_URL_PATH);

        return $path ?: '/student/';
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function icons(): array
    {
        $icons = [];

        foreach ([192, 512] as $size) {
            if (file_exists(public_path('pwa/icon-' . $size . '.png'))) {
                $icons[] = [
                    'src' => asset('pwa/icon-' . $size . '.png'),
                    'sizes' => $size . 'x' . $size,
                    'type' => 'image/png',
                    'purpose' => 'any',
                ];
            }
        }

        // Android masks icons into a circle; without a maskable one it crops the
        // logo's edges off.
        if (file_exists(public_path('pwa/icon-maskable-512.png'))) {
            $icons[] = [
                'src' => asset('pwa/icon-maskable-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        return $icons;
    }

    protected function shorten(string $name): string
    {
        return mb_strlen($name) <= 12 ? $name : mb_substr($name, 0, 12);
    }
}
