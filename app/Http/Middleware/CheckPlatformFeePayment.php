<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\PlatformFeeAccess;
use Illuminate\Support\Facades\Auth;

class CheckPlatformFeePayment
{
    public function __construct(protected PlatformFeeAccess $access)
    {
    }

    /**
     * Handle an incoming request.
     *
     * The fee is charged once per academic session. This used to look the
     * payment up by student_enroll_id, and a student gets a new enrolment every
     * semester — so progressing put the portal behind the paywall again, in the
     * same year they had already paid for. PlatformFeeAccess asks about the
     * student and the session instead.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('student')->check()) {
            return $next($request);
        }

        if ($this->access->grantsAccess(Auth::guard('student')->user())) {
            return $next($request);
        }

        return redirect()->route('student.platform-fee.payment')
            ->with('warning', 'Please complete your platform access fee payment to access the portal.');
    }
}
