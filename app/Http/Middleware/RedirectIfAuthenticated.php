<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Closure;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {

            if($guard == "student"){

                //student was authenticated with student guard.
                return redirect()->route('student.dashboard.index');
            } elseif($guard == "applicant"){

                //applicant was authenticated with applicant guard.
                return redirect()->route('application.dashboard');
            } elseif($guard == "web"){

                //staff was authenticated with web guard.
                return redirect()->route('admin.dashboard.index');
            } else {

                //default guard.
                return redirect()->route('admin.dashboard.index');
            }

        }

        return $next($request);
    }
}
