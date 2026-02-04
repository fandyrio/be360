<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSatkerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user_role=$request->user()->IdRole;
        if($user_role && ((int)$user_role !== 3 && (int)$user_role !== 4)){
            return response()->json(['message'=>'Access Denied'], 403);
        }
        return $next($request);
    }
}
