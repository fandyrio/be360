<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Vinkla\Hashids\Facades\Hashids;

class ThrottleIsiSurvey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {        
        $unique = hash('sha256', request()->ip() . request()->header('User-Agent'));
        $key="survey_throttle1_".$unique;


        $exceuted=RateLimiter::attempt(
                $key,
                20,
                function(){
                    return true;
                },
                120
        );
        
        if(!$exceuted){
            $msg="Mohon menunggu sebentar... ";
            return response()->json(['status'=>false, 'msg'=>$msg],  429);
        }
        return $next($request);
        
}
}
