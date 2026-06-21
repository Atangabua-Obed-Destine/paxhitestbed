<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if HTTPS enforcement is enabled in environment
        if (!config('app.force_https', false)) {
            return $next($request);
        }

        // Skip for localhost/development
        if (app()->environment('local') || $request->ip() === '127.0.0.1') {
            return $next($request);
        }

        // Redirect to HTTPS if not already secure
        if (!$request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
