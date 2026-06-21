<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class UpdateLastSeen
{
    /**
     * Update the authenticated user's last_seen_at timestamp.
     * Throttled to once per minute to avoid excessive DB writes.
     */
    public function handle($request, Closure $next)
    {
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if (!$user->last_seen_at || $user->last_seen_at->diffInMinutes(now()) >= 1) {
                $user->update(['last_seen_at' => now()]);
            }
        } elseif (Auth::guard('student')->check()) {
            $user = Auth::guard('student')->user();
            if (!$user->last_seen_at || $user->last_seen_at->diffInMinutes(now()) >= 1) {
                $user->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
