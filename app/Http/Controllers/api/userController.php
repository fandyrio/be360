<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tref_users;
use App\Services\userService;
use App\Models\Satker;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class userController extends Controller
{

    protected $userService;

    public function __construct(userService $user_service){   
        $this->userService=$user_service;
    }

    public function getUserDetil(Request $request){
        $status=401;
        $data=[];
        $user=$request->user();
        if(isset($user) && !is_null($user)){
            $user_id=$request->user()->IdUser;
            $get_data=Tref_users::join('tref_roles', 'tref_roles.IdRole', '=', 'tref_users.IdRole')
                            ->select('tref_users.NamaLengkap', 'tref_users.NIPBaru', 'tref_roles.rolename', 'tref_users.IdSatker', 'tref_roles.code')
                            ->where('tref_users.IdUser', $user_id)
                            ->where('tref_users.is_active', true)
                            ->first();
            //user type:
            //1. superadmin
            //2. badilum
            //3. admin
            $required_filled_pegawai=false;
            if(!is_null($get_data)){
                if((int)$get_data['IdPegawai'] === 0 && (int)$get_data['IdRole'] >= 3){
                    $required_filled_pegawai=true;
                }
                $data['nama']=$get_data['NamaLengkap'];
                $data['nip']=$get_data['NIPBaru'];
                $data['user_type']=$get_data['rolename'];
                $data['role_code']=$get_data['code'];
                $data['id_satker']=$get_data['IdSatker'];
                $data['data_pegawai']=$required_filled_pegawai;
                $data['nama_satker']='';
                return response()->json(['status'=>200, 'message'=>'success', 'data'=>$data]);
            }
        }
        return response()->json(['status'=>401, 'message'=>'Data not Found', 'data'=>$data]);
    }
    
    public function getAllUser(Request $request, $page = null){
        $user_id=$request->user()->IdUser;
        $isSuperAdmin=$this->userService->isSuperAdmin($user_id);
        $isAdminBadilum=$this->userService->isAdminBadilum($user_id);
        if($isSuperAdmin === true || $isAdminBadilum === true){
            $get_data=$this->userService->getAllDataUser($page, $isSuperAdmin);
            return response()->json($get_data);
        }else{
            return response()->json(['status'=>false, 'message'=>'Access Denied']);
        }
    }

    public function getSatkerBanding(){
        $list_banding=$this->userService->getSatkerBanding();
        return response()->json($list_banding);
    }

    public function getSatkerTKPertama($id_banding): JsonResponse{
        $list_satker_pertama=$this->userService->getSatkerTKPertama($id_banding);
        return response()->json($list_satker_pertama);
    }

    public function saveAdminUser(Request $request): JsonResponse{
        $status=false;
        try{
            $validate=$request->validate([
                'username'=> ['required', 'string'],
            ]);
            $save_user=$this->userService->saveUserDefault($request);
            $status=$save_user['status'];
            $msg=$save_user['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'message'=>$msg]);
    }

    public function getUserById(Request $request, $user_id){
        $status=false;
        $data=[];
        try{
            $request->merge(['id'=>$user_id]);
            $validate=$request->validate([
                'id'=>['required', 'string', 'min:8']
            ]);
            $user_id_logged=$request->user()->IdUser;
            try{
                $dec_user_id=Hashids::decode($user_id);
                if(empty($dec_user_id)){
                    throw new \Exception('Invalid token');
                }
                $get_data=$this->userService->getUserById($dec_user_id[0], $user_id_logged);
                $status=$get_data['status'];
                $msg=$get_data['msg'];
                $data=$get_data['data'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'message'=>$msg, 'data'=>$data]);
    }

    public function updateDataUser(Request $request): JsonResponse{
        $status=false;
        try{
            $request->validate([
                'password'=>['nullable', 'string', 'min:6', 'same:password_confirmation'],
                'password_confirmation'=>['nullable', 'string', 'min:6'],
                'active'=>['required', 'max:1', 'in:Y,N'],
                'user_enc'=>['required', 'min:8', 'string']
            ]);
            $user_id_logged=$request->user()->IdUser;
            try{
                $user_id=Hashids::decode($request->user_enc);
                if(empty($user_id)){
                    return throw new \Exception('Invalid token');
                }
                $update_data=$this->userService->updateDataUser($request, $user_id, $user_id_logged);
                $status=$update_data['status'];
                $msg=$update_data['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'message'=>$msg]);
    }

    public function generateUserBadilum(){
        $get_data=Satker::where('IdSatker', 3)->first();
        $kode_satker=$get_data['KodeSatker'];
        $password= Hash::make($kode_satker);
        $user=new Tref_users;
        $user->UName=$kode_satker;
        $user->IdPegawai=0;
        $user->NamaLengkap="Admin Badilum";
        $user->NIPBaru="-";
        $user->IdRole=2;
        $user->Passwd=$password;
        $user->PasswdTemp_activation=false;
        $user->IdNamaJabatan=0;
        $user->IdSatker=3;
        $user->diinput_tgl=date('Y-m-d H:i:s');
        $user->diinput_oleh='system';
        $user->diperbarui_tgl=date('Y-m-d H:i:s');
        $user->diperbarui_oleh="system";
        $user->is_active=true;
        $user->Email="adminbadilum@test";
        $user->save();
    }

    public function generateUserSystem($id_banding){
        $get_data=Satker::where('IdSatker', 3)->first();
        $kode_satker=$get_data['KodeSatker'];
        $password= Hash::make($kode_satker);
        $user=new Tref_users;
        $user->UName=$kode_satker;
        $user->IdPegawai=0;
        $user->NamaLengkap="Admin Badilum";
        $user->NIPBaru="-";
        $user->IdRole=2;
        $user->Passwd=$password;
        $user->PasswdTemp_activation=false;
        $user->IdNamaJabatan=0;
        $user->IdSatker=3;
        $user->diinput_tgl=date('Y-m-d H:i:s');
        $user->diinput_oleh='system';
        $user->diperbarui_tgl=date('Y-m-d H:i:s');
        $user->diperbarui_oleh="system";
        $user->is_active=true;
        $user->Email="adminbadilum@test";
        $user->save();
    }

    public function getDetilUserSatker(Request $request){
        $username=$request->user()->uname;
        $get_data=$this->userService->getDataUserSatker($username);
        $status=$get_data['status'];
        $msg=$get_data['msg'];
        $signature=$get_data['signature'];
        $data=$get_data['data'];

        return response()->json(['status'=>$status, 'msg'=>$msg, 'signature'=>$signature, 'data'=>$data]);
        
    }

    public function getDataPegawaiByNIP(Request $request){
        $status=false;
        $data=[];
        try{
            $request->validate([
                'nip'=>['required', 'string', 'size:18']
            ]);
            $id_satker=$request->user()->IdSatker;
            $get_pegawai=$this->userService->getAdminSatkerByNIP($request->nip, $id_satker);
            $status=$get_pegawai['status'];
            $msg=$get_pegawai['msg'];
            $data=$get_pegawai['data'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function sendTokenWaNewAdmin(Request $request){
        $status=false;
        $expired_at=null;
        try{
            $request->validate([
                'nip'=>['required', 'size:18'],
                'token_user'=>['required'],
                'payload'=>['required']
            ]);
            $token_user=Hashids::decode($request->token_user);
            if(empty($token_user)){
                return response()->json(['status'=>false, 'msg'=>"Invalid Token User"]);
            }
            $id_satker=$request->user()->IdSatker;
            $category="new_admin";
            $nip=$request->nip;
            $send_token=$this->userService->sendWaToken($id_satker, $category, $nip, $token_user[0]);
            $status=$send_token['status'];
            $msg=$send_token['msg'];
            $expired_at=$send_token['expired_at'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }   

        return response()->json(['status'=>$status, 'msg'=>$msg, 'expired_at'=>$expired_at]);
    }

    public function confirmToken(Request $request){
        $status=false;
        try{
            $request->validate([
                'token'=>['required', 'digits:6'],
                'token_user'=>['required'],
                'category'=>['required'],
                'payload'=>['required']
            ]);
            $id_user=Hashids::decode($request->token_user);
            if(empty($id_user)){
                return response()->json(['status'=>false, 'msg'=>'Invalid token user']);
            }
            $id_satker=$request->user()->IdSatker;
            $category=$request->category;
            $token=$request->token;
            $confirm=$this->userService->confirmToken($id_satker, $category, $token, $id_user[0]);
            $status=$confirm['status'];
            $msg=$confirm['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

   

    public function saveAdminSatker(Request $request){
        $status=false;
        try{
            $request->validate([
                'nip'=>['required', 'string', 'size:18'],
                'token_user'=>['required'],
                'payload'=>['required'],
                'password'=>['required'],
                'repassword'=>['required']
            ]);
            $id_satker=$request->user()->IdSatker;
            try{
                $id_user=Hashids::decode($request->token_user);
                if(empty($id_user)){
                    throw new \Exception("Invalid Token User");
                }
                if($request->password === $request->repassword){
                    $save_admin=$this->userService->saveAdmin($request->nip, $id_satker, $id_user[0], $request->password);
                    $status=$save_admin['status'];
                    $msg=$save_admin['msg'];
                }else{
                    $msg="Password Harus sama";
                }
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function generateUserAdminSatker(){
        $generate=$this->userService->generateUserAdminSatker();
        $status=$generate['status'];
        $msg=$generate['msg'];

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }
}
