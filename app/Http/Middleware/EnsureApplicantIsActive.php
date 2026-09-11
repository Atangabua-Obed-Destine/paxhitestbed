<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign out an applicant whose account has been disabled.
 *
 * Refusing the login is not enough on its own: an applicant who was already
 * signed in when the account was disabled would carry on until the session
 * expired. This checks on every portal request, so the next click ends it.
 *
 * Only the applicant guard is signed out. The session is shared with the admin
 * guard, and invalidating it would also sign out an administrator using the
 * same browser — which is exactly what happens while testing a disabled
 * account.
 */
class EnsureApplicantIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $applicant = Auth::guard('applicant')->user();

        if ($applicant && $applicant->disabled_at) {
            Auth::guard('applicant')->logout();
            $request->session()->regenerateToken();

            $message = __('This account has been disabled. Please contact the admissions office.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('application.login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
