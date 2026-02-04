<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class dashboardController extends Controller
{
    public function dashboardAdminSatker(Request $request){
        $role=$request->user()->IdRole;
        if($role === 3 || $role === 4){
            if(!checkDataAdminSatker($request->user()->uname)){
                return response()->json(['status'=>false, 'msg'=>'Silahkan Melengkapi data Admin Terlebih dahulu']);
            }
        }
        

        return response()->json(['status'=>true, 'msg'=>"Access Valid", 'data'=>[]]);
    }

    public function dashboardAdminBadilum(Request $request){
        return response()->json(['status'=>true, 'msg'=>"Access Valid"]);
    }
}
