<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tref_users;
use App\Models\Idtref_roles;
class loginController extends Controller
{
    public function login(Request $request){
        try{
            $validate=$request->validate([
                'username'=>['required', 'min:5'],
                'password'=>['required', 'min:5']
            ]);
            $get_data=Tref_users::where('Uname', $request->username)
                        ->join('idtref_roles', function($join){
                            $join->on('idtref_roles.IdRole', '=', 'tref_users.IdRole')
                                ->where('idtref_roles.is_active', true);
                        })
                        ->select('tref_users.*', 'idtref_roles.rolename')
                        ->first();
            if(!is_null($get_data)){
                $check_pwd=Hash::check($request->password, $get_data['passwd']);
                // $check_pwd=true;
                if($check_pwd){
                    $user=Tref_users::where('IdUser', $get_data['IdUser'])->first();
                    $token=JWTAuth::fromUser($user);
                    $refreshToken=JWTAuth::claims(['type'=>'refresh'])->fromUser($user);

                    return response()->json(['message'=>'Login Berhasil', 'token'=>$token, 'status'=>200])->withCookie((cookie('rft', $refreshToken, 60*24*7, '/', null, true, true, false, 'Lax')));
                }else{
                    $msg="Username and Password doesn't matched 1";
                }
            }else{
                $msg="Username and Password doesn't matched";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['message'=>$msg, 'status'=>401]);
    }

    public function refreshToken(Request $request){
        $refresh_token=$request->cookie('rft');
        try{
            $payload=JWTAuth::setToken($refresh_token)->getPayLoad();
            if($payload->get('type') !==  "refresh"){
                return response()->json(['message'=>'Access denied'], 400);
            }
            $user=JWTAuth::setToken($refresh_token)->toUser();
            $newToken=JWTAuth::fromUser($user);

            return response()->json(['token'=>$newToken], 200);
        }catch(\Exception $e){
            return response()->json(['message'=>'Invalid or Expired Token '.$e->getMessage()], 401)->withCookie(cookie('rft', null, -1, '/', null, true, true,  false, 'Lax'));
            
        }

    }

    public function logout(){
        try{
            $token=JWTAuth::parseToken();
            JWTAuth::invalidate($token);

            return response()->json([
                'status' => 200,
                'message' => 'Logged Out'
            ])->withCookie(
                cookie('rft', null, -1, '/', null, false, true, false, 'Lax')
            );
        }catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token not provided'], 400);
        }
    }

    // public function generateAccount(){
    //     $password= Hash::make(' ');
    //     $user=new Tref_users;
    //     $user->UName="@dm1n";
    //     $user->IdPegawai=0;
    //     $user->NamaLengkap="Super Administrator";
    //     $user->NIPBaru="-";
    //     $user->IdRole=1;
    //     $user->Passwd=$password;
    //     $user->PasswdTemp_activation=false;
    //     $user->IdJabatan=0;
    //     $user->IdSatker=0;
    //     $user->diinput_tgl=date('Y-m-d H:i:s');
    //     $user->diinput_oleh='system';
    //     $user->diperbarui_tgl=date('Y-m-d H:i:s');
    //     $user->diperbarui_oleh="system";
    //     $user->is_active=true;
    //     $user->Email="superadmin@super";
    //     $user->save();
    // }
}
