<?php

namespace App\Http\Middleware;

use App\Models\Tref_users;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Tref_zonasi;

class JWTMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try{
            $user=JWTAuth::parseToken()->authenticate();
        }catch(JWTException $e){
            return response()->json(['message'=>'Unauthorized'], 401);
        }
        return $next($request);
    }
}
