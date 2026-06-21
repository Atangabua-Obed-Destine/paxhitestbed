<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;

class LicenseVerification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $license = config('services.envato.license');

        if(strlen($license) != 32){

            return redirect()->route('verify');
        }

        return $next($request);
    }
}
