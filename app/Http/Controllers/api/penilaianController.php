<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trans_observee;
use App\Services\penilaianService;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class penilaianController extends Controller
{
    protected $penilaianService;

    public function __construct(penilaianService $penilaian_serivice){
        $this->penilaianService=$penilaian_serivice;
    }

    public function validateParams(Request $request){
        $status=false;
        $signature="";
        $endpoint="";
        $token_penilaian="";
        try{
            $request->validate([
                'data_token'=>['required', 'string']
            ]);
            $explode_token=explode("-", $request->data_token);
            $jlh_token=count($explode_token);
            if($jlh_token === 3){
                try{
                    $id_pegawai=Hashids::decode($explode_token[0]);
                    $id_nama_jabatan=Hashids::decode($explode_token[1]);
                    $id_zonasi_satker=Hashids::decode($explode_token[2]);
                    $real_id_nama_jabatan=(int)$id_nama_jabatan[0] - (int)$id_pegawai[0];
                    $real_id_zonasi_satker=(int)$id_zonasi_satker[0] - (int)$id_pegawai[0];
                    if(empty($id_pegawai) || empty($id_nama_jabatan) || empty($id_zonasi_satker)){
                        throw new \Exception("Invalid token. Kesalahan ini telah direkam. Mohon menghubungi Administrator");
                    }
                $validateParams=$this->penilaianService->validateParamsPenilaian($id_pegawai[0], $real_id_nama_jabatan, $real_id_zonasi_satker);
                    $status=$validateParams['status'];
                    $signature=$validateParams['signature'];
                    $endpoint=$validateParams['endpoint'];
                    $token_penilaian=$validateParams['token_penilaian'];
                    $msg=$validateParams['msg'];
                }catch(\Exception $e){
                    $msg=$e->getMessage();
                }
            }else{
                $msg="Data token tidak valid";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'endpoint'=>$endpoint, 'signature'=>$signature, 'token_penilaian'=>$token_penilaian]);
    }

    public function penilaian(Request $request){
        $status=false;
        $total=0;
        $data=null;
        $endpoint_penilain=null;
        try{
            $request->validate([
                'endpoint'=>['required'],
                'token_penilaian'=>['required'],
                'payload'=>['required']
            ]);
            $endpoint=$request->endpoint;
            try{
                $id_observee_decrypt=Crypt::decrypt($endpoint);
                $id_observee_decode=Hashids::decode($id_observee_decrypt);
                
                $explode_token=explode("atAMObE", $request->token_penilaian);
                $jlh_explode=count($explode_token);
                if($jlh_explode === 2){
                    $nip_penilai=Hashids::decode($explode_token[0]);
                    $id_zonasi_satker=Hashids::decode($explode_token[1]);
                    if(empty($id_observee_decode) || empty($nip_penilai) || empty($id_zonasi_satker)){
                        return response()->json(['status'=>false, "msg"=>"Data tidak ditemukan. Kesalahan ini telah direkam. Mohon menghubungi Administrator"]);
                    }
                    $get_penilaian=$this->penilaianService->getDataPenilaian($id_observee_decode[0], $nip_penilai[0], $id_zonasi_satker[0]);
                    $status=$get_penilaian['status'];
                    $msg=$get_penilaian['msg'];
                    $total=$get_penilaian['total'];
                    $selesai=$get_penilaian['selesai'];
                    $data=$get_penilaian['data'];
                    $endpoint_penilain=$get_penilaian['token_r'];
                }else{
                    $msg="Data token tidak valid";
                }
            }catch(DecryptException $e){
                $msg="Data Penilaian tidak ditemukan. Kesalahan ini telah direkam. Mohon menghubungi Administrator";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'total'=>$total, 'selesai'=>$selesai, 'token_r'=>$endpoint_penilain, 'data'=>$data]);
    }

    public function listPertanyaanPenilaian(Request $request){
        $status=false;
        $keterangan="";
        $data=[];
        $signature="";
        try{
            $request->validate([
                'payload'=>['required', 'string'],
                'token_penilaian'=>['required', 'string'],
                'params'=>['required']
            ]);
            $params=$request->params;
            try{
                $params_dec=Crypt::decrypt($params);
                $explode=explode("paramsdata", $params_dec);
                $jlh_explode=count($explode);
                if($jlh_explode === 3){
                    $id_observee_peserta=$explode[0];
                    $id_observee_penilai=$explode[1];
                    $id_zonasi_satker_params=$explode[2];
                    
                    $token_penilaian=$request->token_penilaian;
                    $explode_token_penilain=explode("atAMObE", $token_penilaian);
                    
                    if(count($explode_token_penilain) === 2){
                        $nip_penilai_hashed=$explode_token_penilain[0];
                        $id_zonasi_hashed=$explode_token_penilain[1];
                        $nip_penilai=Hashids::decode($nip_penilai_hashed);
                        $id_zonasi_satker_token=Hashids::decode($id_zonasi_hashed);
                        
                        //create new signature
                        // $pemisahString=$nip_penilai_hashed."l15TQtSN".$id_zonasi_hashed;
                        $pemisahString[0]=Hashids::encode(substr(time(). random_int(100, 999), -6));
                        $pemisahString[1]=Hashids::encode(substr(time(). random_int(100, 999), -6));
                        $pemisahString[2]=Hashids::encode(substr(time(). random_int(100, 999), -6));
                        
                        if(empty($nip_penilai) || empty($id_zonasi_satker_token)){
                            return response()->json(['status'=>false, 'msg'=>"Invalid token Penilaian"], 500);
                        }

                        //rules
                        $get_jabatan=$this->penilaianService->getJabatanPesertaPenilai($id_observee_penilai, $id_observee_peserta);
                        if((int)$id_zonasi_satker_token[0] === (int)$id_zonasi_satker_params || $get_jabatan === true){
                            if((int)$id_zonasi_satker_token[0] === (int)$id_zonasi_satker_params){
                                $id_zonasi_satker_=$id_zonasi_satker_token[0];
                            }else{
                                $id_zonasi_satker_=$id_zonasi_satker_params;
                            }
                            $get_pertanyaan=$this->penilaianService->getPertanyaanPenilaian($id_observee_penilai, $id_observee_peserta, $nip_penilai[0], $id_zonasi_satker_, $pemisahString);
                            $status=$get_pertanyaan['status'];
                            $msg=$get_pertanyaan['msg'];
                            $can_edit=$get_pertanyaan['can_edit'];
                            $signature_periode=$get_pertanyaan['signature'];
                            $id_pz_string=$get_pertanyaan['params'];
                            $token_penilaian_periode=$get_pertanyaan['token_penilaian'];
                            $keterangan=$get_pertanyaan['keterangan'];
                            $data=$get_pertanyaan['data'];
                            $peserta=$get_pertanyaan['peserta'];
                            
                        }else{
                            $msg="Data Zonasi Satker tidak valid ";
                            return response()->json(['status'=>$status, 'msg'=>$msg], 500);
                        }
                    }else{
                        $msg="Invalid token Penilaian Format";
                        return response()->json(['status'=>$status, 'msg'=>$msg], 500);
                    }                    
                }else{
                    $msg="Invalid token Params -";
                    return response()->json(['status'=>$status, 'msg'=>$msg], 500);
                }
            }catch(DecryptException $e){
                $msg="Invalid token Params";
                return response()->json(['status'=>$status, 'msg'=>$msg], 500);
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
            return response()->json(['status'=>$status, 'msg'=>$msg], 500);
        }

        return response()->json(['status'=>$status, 'can_edit'=>isset($can_edit) ? $can_edit : false, 'msg'=>$msg, 'params'=>$id_pz_string, 'token_penilaian'=>$token_penilaian_periode, 'signature'=>$signature_periode, 'keterangan'=>$keterangan, 'data'=>$data, 'peserta'=>$peserta]);
    }

    public function saveJawaban(Request $request){
        $status=false;
        $msg="";
        $fetch_headers=null;
        $fetch_jawaban=null;
        try{
            $request->validate([
                'payload'=> ['required', 'string'],
                'token_penilaian'=> ['required', 'string'],
                'id_jawaban'=>['required', 'string']
            ]);
            //fetch Header s
            $fetch_headers=$this->fetchTokenPayload($request->token_penilaian);
            if($fetch_headers['status'] === true){
                $fetch_jawaban=$this->fetchIdJawaban($request->id_jawaban, $fetch_headers['pemisahString_1'], $fetch_headers['pemisahString_2'], $fetch_headers['pemisahString_3']);
                if($fetch_jawaban['status'] === true){
                    $id_zs_payload=$fetch_headers['id_zonasi_satker'];
                    $id_zs_jawaban=$fetch_jawaban['id_zs'];
                    $can_edit=$fetch_headers['can_edit'];
                    $id_pertanyaan=$fetch_jawaban['id_pertanyaan'];
                    $id_pertanyaan_periode = $fetch_headers['id_pertanyaan_periode'];
                    if((int)$id_zs_payload !== (int)$id_zs_jawaban || $can_edit === 0 || (int)$id_pertanyaan !== $id_pertanyaan_periode){
                        return response()->json(['status'=>false, 'msg'=>"Jawaban tidak dapat disimpan"]);
                    }
                    $save_jawaban=$this->penilaianService->saveJawaban($fetch_headers['periode_id'], $fetch_headers['id_ref_pertanyaan'], $id_pertanyaan_periode, $id_zs_payload, $fetch_jawaban['id_jawaban'], $fetch_jawaban['point_jawaban'], $fetch_jawaban['id_nilai'], $fetch_jawaban['id_pz_hashed']);
                    $status=$save_jawaban['status'];
                    $msg=$save_jawaban['msg'];
                }else{
                    $msg.=$fetch_jawaban['msg'];
                }
            }else{
                $msg.=$fetch_headers['msg'];
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }



    public function fetchTokenPayload($token_penilaian){
        $status=false;
        try{
            $hasil=preg_split("/AtaM063|3d1t4Bl3|pzh45H3d/", $token_penilaian);
            if(count($hasil) !== 6){
                throw new \Exception("Invalid data");
            }
            $token_payload_1=$hasil[0];
            $pemisahString_1=$hasil[1];
            $pemisahString_2=$hasil[2];
            $pemisahString_3=$hasil[3];
            $can_edit=Hashids::decode($hasil[4]);
            $id_zonasi_satker=Hashids::decode($hasil[5]);
            $hasil_2=preg_split("/$pemisahString_1|$pemisahString_2|$pemisahString_3/", $token_payload_1);
            if(count($hasil_2) !== 4){
                throw new \Exception("Invalid data");
            }
            $pertanyaan_periode=Hashids::decode($hasil_2[1]);
            $ref_pertanyaan=Hashids::decode($hasil_2[2]);
            $periode_id=Hashids::decode($hasil_2[3]);

            if(empty($periode_id) || empty($ref_pertanyaan) || empty($pertanyaan_periode) || empty($can_edit) || empty($id_zonasi_satker)){
                throw new \Exception("Invalid data");
            }
            $status=true;
            return [
                'status'=>$status,
                'msg'=>'',
                'periode_id'=>$periode_id[0],
                'id_ref_pertanyaan'=>$ref_pertanyaan[0],
                'id_pertanyaan_periode'=>$pertanyaan_periode[0],
                'pemisahString_1'=>$pemisahString_1,
                'pemisahString_2'=>$pemisahString_2,
                'pemisahString_3'=>$pemisahString_3,
                'can_edit'=>$can_edit[0],
                'id_zonasi_satker'=>$id_zonasi_satker[0]
            ];
        }catch(\Exception $e){
            $msg="Invalid token";
        }
        return [
            'status'=>$status,
            'msg'=>$msg
        ];
    }

    public function fetchIdJawaban($id_jawaban, $pemisahString_1, $pemisahString_2, $pemisahString_3){
        try{
            $hasil=preg_split("/-|$pemisahString_1|$pemisahString_2|$pemisahString_3/", $id_jawaban);
            if(count($hasil) !== 5){
                throw new \Exception("Invalid token peserta:1");
            }
            $id_jawaban=Hashids::decode($hasil[0]);
            $point_jawaban=Hashids::decode($hasil[1]);
            $id_nilai=Hashids::decode($hasil[2]);
            $id_pertanyaan=Hashids::decode($hasil[3]);
            $id_peserta_zonasi_temp=$hasil[4];
            $explode_pz_zs=explode("idZzh45h3d", $id_peserta_zonasi_temp);
            if(count($explode_pz_zs) !== 2){
                throw new \Exception("Invalid token peserta : 2");
            }
            $id_pz_temp=$explode_pz_zs[0];
            $explode_pz=explode("5pr4t3Pzh45H3d", $id_pz_temp);
            $id_pz=[];
            for($x=0;$x<count($explode_pz);$x++){
                $id_pz[]=Hashids::decode($explode_pz[$x])[0];
                if(empty(Hashids::decode($explode_pz[$x]))){
                    throw new \Exception("Invalid token peserta");
                }
            }
            $id_zs=Hashids::decode($explode_pz_zs[1]);

            if(empty($id_jawaban) || empty($point_jawaban) || empty($id_nilai) || empty($id_pertanyaan) || empty($id_zs)){
                throw new \Exception("Invalid token");
            }
            $status=true;
            return [
                'status'=>$status,
                'msg'=>'',
                'id_jawaban'=>$id_jawaban[0],
                'point_jawaban'=>$point_jawaban[0],
                'id_nilai'=>$id_nilai[0],
                'id_pertanyaan'=>$id_pertanyaan[0],
                'id_pz_hashed'=>$id_pz,
                'id_zs'=>$id_zs[0]
            ];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return [
            'status'=>false,
            'msg'=>$msg
        ];
        
    }

    public function lockJawaban(Request $request){
        $status=false;
        $msg="";
        $fetch_headers=null;
        $fetch_jawaban=null;
        try{
            $request->validate([
                'payload'=> ['required', 'string'],
                'token_penilaian'=> ['required', 'string'],
                'params'=>['required', 'string']
            ]);
            //fetch Header s
            $fetch_headers=$this->fetchTokenPayloadLocked($request->token_penilaian);
            if($fetch_headers['status'] === true){
                 $fetch_jawaban=$this->fetchParamsLocked($request->params, $fetch_headers['data']['id_periode_1_hashed']);
                 if($fetch_jawaban['status'] === true){
                    $id_zs_payload=$fetch_headers['data']['id_zonasi_satker'];
                    $id_pz=$fetch_headers['data']['id_pz'];//id peserta zonasi bisa lebih dari 1 karena ada yang mengisi sebagai PLT
                    $id_periode=$fetch_headers['data']['id_periode'];
                    $can_edit=$fetch_headers['data']['can_edit'];

                    $id_nilai_peserta=$fetch_jawaban['data'];
                    
                    if($can_edit === 0){
                        return response()->json(['status'=>false, 'msg'=>"Jawaban tidak dapat disimpan"]);
                    }
                    $save_jawaban=$this->penilaianService->lockJawaban($id_periode, $id_zs_payload, $id_pz,$id_nilai_peserta);
                    $status=$save_jawaban['status'];
                    $msg=$save_jawaban['msg'];
                 }else{
                    $msg.=$fetch_jawaban['msg']." ";
                 }
            }else{
                $msg.=$fetch_headers['msg']." ";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function fetchTokenPayloadLocked($token_penilaian){
        $data=[];
        $status=false;
        $msg="";
        try{
            $explode_token_penilain=preg_split("/-/", $token_penilaian);
            if(count($explode_token_penilain) !== 5){
                throw new \Exception('Data tidak valid :1');
            }
            $id_periode_1=Hashids::decode($explode_token_penilain[0]);//+1
            $id_zonasi_satker=Hashids::decode($explode_token_penilain[1]);
            $id_pz_temp=$explode_token_penilain[2];
            $explode_pz=explode("5pr4t3Pzh45H3d", $id_pz_temp);
            $jlh_peserta_zonasi=count($explode_pz);
            $pz=[];
            for($a=0;$a<$jlh_peserta_zonasi;$a++){
                $pz[]=Hashids::decode($explode_pz[$a])[0];
            }
            $id_periode=Hashids::decode($explode_token_penilain[3]);
            $can_edit=Hashids::decode($explode_token_penilain[4]);
            if(empty($id_periode_1) || empty($id_zonasi_satker) || empty($id_periode)){
                throw new \Exception("Data tidak valid :2");
            }
            $compare_periode=$id_periode_1[0] - 1;
            if($compare_periode !== $id_periode[0]){
                throw new \Exception("Data tidak valid :2");
            }
            $status=true;
            $data=[
                'id_periode'=>$compare_periode,
                'id_zonasi_satker'=>$id_zonasi_satker[0]-1,
                'id_pz'=>$pz,
                'id_periode_1'=>$id_periode_1[0],
                'id_periode_1_hashed'=>$explode_token_penilain[0],
                'can_edit'=>$can_edit[0]
            ];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }
        
        return [
            'status'=>$status,
            'msg'=>$msg,
            'data'=> $data
        ];
    }

    public function fetchParamsLocked($params, $id_periode_1){
        $data=[];
        $status=false;
        $msg="";
        $explode=preg_split("/$id_periode_1/", $params);
        $jumlah_locked=count($explode);
        try{
            for($x=0;$x<$jumlah_locked-1;$x++){
                $decode=Hashids::decode($explode[$x]);
                if(empty($decode)){
                    throw new \Exception("Data tidak valid. Code: 11");
                    break;
                }
                $data[]=$decode[0];
            }
        $status=true;
        }catch(\Exception $e){
            $status=false;
            $msg=$e->getMessage();
        }
        

        return [
            'status'=>$status,
            'msg'=>$msg,
            'data'=>$data
        ];
    }

    public function reportPenilaianPersonal($key){
        $dec_key=decKeyReportIndividu($key);
        if($dec_key !== 0){
            
        }else{
            $msg="Data tidak valid";
        }
    }

    
}
