<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature=$request->header('X-Signature');
        if(!$signature){
            return response()->json(['msg'=>'Konfigurasi Sistem tidak terpenuhi. Mohon hubungi Tim Pengembang.'], 401);
        }
        $secret=config('app.hmac_secret');
        $var_payload=$request->payload;
        if($var_payload === "data_variable_pertanyaan"){
            $var_payload = "token_penilaian";
        }
        $payload=json_encode(['payload'=>$request->$var_payload]);
        $hash=hash_hmac('sha256', $payload, $secret);
        if(!hash_equals($hash, $signature)){
            return response()->json(['msg'=>'Akses tidak dapat diberikan. Silahkan Refresh atau kembali ke halaman utama '], 401);
        }
        return $next($request);
    }
}
