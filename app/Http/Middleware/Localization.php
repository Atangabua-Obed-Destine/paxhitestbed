<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use App\Models\Language;
use Closure;

class Localization
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

       if(Session::has('locale'))
       {
           App::setlocale(Session::get('locale'));
       }
       else
       {
           $language = Language::where('default', '1')->first();
           
           if(isset($language->code))
           {
               App::setlocale($language->code);
           }
       }

       return $next($request);
    }
}
