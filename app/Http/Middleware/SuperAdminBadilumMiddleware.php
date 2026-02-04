<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminBadilumMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user=$request->user()->IdRole;
        if(!$user || ((int)$user !== 2 && (int)$user !== 1)){
            return response()->json(['message' => 'Access Denied'], 403);
        }
        return $next($request);
    }
}
