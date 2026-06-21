<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Check if user needs 2FA verification
        if ($user && session()->has('2fa_required_' . $user->id)) {
            // Allow access to 2FA verify route
            if (!$request->is('*/verify-2fa') && !$request->is('*/logout')) {
                return redirect()->route($this->get2FARoute());
            }
        }

        return $next($request);
    }

    /**
     * Get the appropriate 2FA route based on guard
     */
    protected function get2FARoute(): string
    {
        if (auth()->guard('web')->check()) {
            return 'admin.verify-2fa';
        } elseif (auth()->guard('student')->check()) {
            return 'student.verify-2fa';
        }
        
        return 'login';
    }
}
