<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\configService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

class configController extends Controller
{
    protected $configService;

    public function __construct(configService $config_service){
        $this->configService=$config_service;
    }

    public function listRole($page=null){
        $get_list=$this->configService->listRole($page);
        return response()->json($get_list);
    }

    public function saveRole(Request $request){
        $status=false;
        try{
            $validate=$request->validate([
                'rolename'=>['required', 'min:4'],
                'active'=>['required', 'max:1', 'in:Y,N']//Y | N
            ]);

            $save_role=$this->configService->saveRole($request);
            $status=$save_role['status'];
            $msg=$save_role['msg'];

        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'message'=>$msg]);
    }

    public function getRoleById($role_id){
        $get_data=$this->configService->getDetilRole($role_id);
        return response()->json($get_data);
    }

    public function updateRole(Request $request){
        $status=false;
        try{
            $validate=$request->validate([
                'role_id'=>['required', 'numeric'],
                'rolename'=>['required', 'min:4'],
                'active'=>['required', 'max:1', 'in:Y,N']
            ]);
            $update=$this->configService->updateRole($request);
            $status=$update['status'];
            $msg=$update['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'message'=>$msg]);
    }

    public function deleteRole(Request $request){
        $status=false;
        try{
            $validate=$request->validate([
                'role_id'=>['required', 'numeric']
            ]);
            $delete=$this->configService->deleteRole($request);
            $status=$delete['status'];
            $msg=$delete['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'message'=>$msg]);
    }

    public function getDataKelompokJabatan($page){
        if(is_numeric($page)){
            if($page < 1){
                $page=1;
            }
            $page=(int)$page;
        }
        $get_data=$this->configService->getKelompokJabatan($page);
        $data=$get_data['data'];
        $jumlah=$get_data['jumlah'];
        $jumlah_halaman=$get_data['jumlah_halaman'];
        return response()->json(['page'=>$page,'jumlah'=>$jumlah, 'data'=>$data, 'jumlah_halaman'=>$jumlah_halaman]);
    }

    public function getKelompokJabatanDetil($id_kelompok_jabatan_hashed){
        $status=false;
        $data=[];
        $msg="";
        $signature="";
        try{
            $id_kelompok_jabatan=Hashids::decode($id_kelompok_jabatan_hashed);
            if(empty($id_kelompok_jabatan)){
                throw new \Exception("Invalid token Jabatan");
            }
            $get_jabatan=$this->configService->getKelompokJabatanDetil($id_kelompok_jabatan[0]);
            $status=$get_jabatan['status'];
            $msg=$get_jabatan['msg'];
            $data=$get_jabatan['data'];
            $signature=$get_jabatan["signature"];
            $status=true;
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, "signature"=>$signature, 'data'=>$data]);
    }

    public function gabungkanKelompokJabatan(Request $request){
        $status=false;
        try{
            $request->validate([
                'id_jabatan'=>['required', 'array'],
                'nama_jabatan'=>['required']
            ]);
            $jumlah_id_jabatan=count($request->id_jabatan);
            if($jumlah_id_jabatan > 1){
                $id_jabatan_arr=[];
                try{
                    for($x=0;$x<$jumlah_id_jabatan;$x++){
                        $decode=Hashids::decode($request->id_jabatan[$x]);
                        if(empty($decode)){
                            throw new \Exception("Data tidak valid");
                        }
                        $id_jabatan_arr[]=$decode[0];
                    }
                    $save_jawaban=$this->configService->gabungkanJabatan($id_jabatan_arr, $request->nama_jabatan);
                    $status=$save_jawaban['status'];
                    $msg=$save_jawaban['msg'];
                }catch(\Exception $e){
                    $msg=$e->getMessage();
                }
            }else{
                $msg="Data tidak bisa digabungkan. Data yang digabungkan harus lebih dari 1";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getKelompokJabatanSIKEP(){
        $get_data=$this->configService->getKelompokJabatanSIKEP();
        return response()->json(['data'=>$get_data]);
    }

    public function saveDataKelompokJabatan(Request $request){
        $status=false;
        try{
            $request->validate([
                "id_kelompok_jabatan"=>['required', 'string'],
                "nama_jabatan"=>['required', 'string']
            ]);
            
            try{
                $id_kelompok_jabatan_dec=Hashids::decode($request->id_kelompok_jabatan);
                if(empty($id_kelompok_jabatan_dec)){
                    throw new \Exception('Invalid token');
                }
                $id_kelompok_jabatan=$id_kelompok_jabatan_dec[0];
                $get_data=$this->configService->saveKelompokJabatan($request, $id_kelompok_jabatan);
                $status=$get_data['status'];
                $msg=$get_data['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    // public function changeActiveKelompokJabatan(Request $request){
    //     $status=false;
    //     try{
    //         $request->validate([
    //             'status'=>['required', 'size:1', 'in:Y,N'],
    //             'id_jabatan_peserta'=> ['required', 'string']
    //         ]);
    //         try{
    //             $id_kelompok_jabatan=Hashids::decode($request->id_jabatan_peserta);
    //             if(empty($id_kelompok_jabatan)){
    //                 throw new \Exception('Invalid token');
    //             }
    //             $id_jabatan_peserta=$id_kelompok_jabatan[0];
    //             $update=$this->configService->changeActiveKelompokJabatan($id_jabatan_peserta, $request->status);
    //             $status=$update['status'];
    //             $msg=$update['msg'];
    //         }catch(\Exception $e){
    //             $msg=$e->getMessage();
    //         }
    //     }catch(ValidationException $e){
    //         $msg=$e->validator->errors()->first();
    //     }

    //     return response()->json(['status'=>$status, 'msg'=>$msg]);
    // }

    public function updateKelompokJabatan(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_jabatan'=>['required', 'string'],
                'jabatan'=>['required', 'string'],
                'active'=> ['required', 'in:Y,N'],
                
            ]);
            $jabatan_gabungan=[];
            try{
                $token_jabatan=Hashids::decode($request->token_jabatan);
                if(empty($token_jabatan)){
                    throw new \Exception("Invalid token :2");
                }
                $jumlah_jabatan_gabungan=count($request->jabatan_gabungan);
                if($jumlah_jabatan_gabungan > 1){
                    $jabatan_gabungan=[];
                    for($i=0;$i<$jumlah_jabatan_gabungan;$i++){
                        $explode_jabatan_gabungan=explode("-", $request->jabatan_gabungan[$i]);
                        if(count($explode_jabatan_gabungan) === 3){
                                //id_jabatan_peserta - id_jabatan_induk - id_kelompok_jabatan_peserta
                            $id_jabatan_gabungan=Hashids::decode($explode_jabatan_gabungan[0]);
                            $id_jabatan_induk=Hashids::decode($explode_jabatan_gabungan[1]);
                            $id_kelompok_jabatan=Hashids::decode($explode_jabatan_gabungan[2]);
                            if(empty($id_jabatan_gabungan) || empty($id_jabatan_induk) ||empty($id_kelompok_jabatan)){
                                throw new \Exception("Invalid token jabatan gabungan");
                            }
                            if((int)$id_jabatan_induk[0] !== $token_jabatan[0]){
                                throw new \Exception("Data jabatan gabungan bukan induk dari jabatan ini ");
                            }
                            $jabatan_gabungan[]=$id_jabatan_gabungan[0];
                        }else{
                            $id_jabatan_gabungan=Hashids::decode($request->jabatan_gabungan[$i]);
                            if(empty($id_jabatan_gabungan)){
                                throw new \Exception("Invalid data jabatan");
                            }
                            $jabatan_gabungan[]=$id_jabatan_gabungan[0];
                            // throw new \Exception("Data Jabatan Gabungan tidak valid");
                        }
                    }
                }elseif($jumlah_jabatan_gabungan === 1){
                    $msg="Jabatan yang digabungkan harus lebih dari 1";
                    throw new \Exception($msg);
                }
                $update_jabatan=$this->configService->updateKelompokJabatan($jabatan_gabungan, $token_jabatan[0], $request->jabatan, $request->active);
                $status=$update_jabatan['status'];
                $msg=$update_jabatan['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function listMappingObservee(){
        $get_mapping=$this->configService->getListMappingJabatan();

        return response()->json(['data'=>$get_mapping['data_mapping']]);
    }

    public function saveMappingJabatan(Request $request){
        $status=false;
        try{
            $request->validate([
                'id_jabatan_peserta'=>['required', 'string'],
                'id_jabatan_penilai'=>['required', 'array'],
                'id_jabatan_penilai.*'=>['string'],
                'threshold'=>['required', 'array'],
                'threshold.*'=>['integer']
            ]);
            try{
                $id_jabatan_peserta=Hashids::decode($request->id_jabatan_peserta);
                if(empty($id_jabatan_peserta)){
                    throw new \Exception('Invalid token Id Jabatan Peserta');
                }

                $jumlah_penilai=count($request->id_jabatan_penilai);
                $jumlah_threshold=count($request->threshold);
                $id_jabatan_penilai_arr=null;
                $threshold_arr=[];
                if($jumlah_penilai === $jumlah_threshold){
                    for($x=0;$x<$jumlah_penilai;$x++){
                        $id_jabatan_penilai=Hashids::decode($request->id_jabatan_penilai[$x]);
                        if(empty($id_jabatan_penilai)){
                            throw new \Exception('Invalid token Id Jabatan Penilai');
                            break;
                        }
                        $id_jabatan_penilai_arr[]=$id_jabatan_penilai[0];
                    }
                    $save_mapping=$this->configService->saveMappingJabatan($id_jabatan_peserta[0], $id_jabatan_penilai_arr, $request->threshold);
                    $status=$save_mapping['status'];
                    $msg=$save_mapping['msg'];
                }else{
                    $msg="Data Penilai terhadap data threshold tidak sesuai";
                }

            }catch(\Exception $e){
                $msg=$e->getMessage()." ".$e->getFile()." ".$e->getLine();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function updateMappingJabatan(Request $request){
        $status=false;
        try{
            $request->validate([
                'id_jabatan_peserta'=>['required', 'string'],
                'id_mapping_jabatan'=> ['required', 'array'],
                'id_mapping_jabatan.*'=>['required', 'string'],
                'id_jabatan_penilai'=>['required', 'array'],
                'id_jabatan_penilai.*'=>['string'],
                'threshold'=>['required', 'array'],
                'threshold.*'=>['integer'],
            ]);
            try{
                $jlh_mapping_jabatan=count($request->id_mapping_jabatan);
                $id_mapping_jabatan=[];
                $new_mapping=0;
                for($a=0;$a<$jlh_mapping_jabatan;$a++){
                    if($request->id_mapping_jabatan[$a] !== "new"){
                        $id_mapping_jabatan_dec=Hashids::decode($request->id_mapping_jabatan[$a]);
                        if(empty($id_mapping_jabatan_dec)){
                            throw new \Exception('Invalid token mapping jabatan ');
                        }
                        $id_mapping_jabatan[]=$id_mapping_jabatan_dec[0];
                    }else{
                        $id_mapping_jabatan[]="new";
                        $new_mapping+=1;
                    }
                }
                $jlh_penilai=count($request->id_jabatan_penilai);
                $id_jabatan_penilai=array();
                for($x=0;$x<$jlh_penilai;$x++){
                    $id_jabatan_penilai_dec=Hashids::decode($request->id_jabatan_penilai[$x]);
                    if(empty($id_jabatan_penilai_dec)){
                        throw new \Exception('Invalid token jabatan penilai');
                        break;
                    }
                    $id_jabatan_penilai[$x]=$id_jabatan_penilai_dec[0];
                }

                $jlh_mapping=count($id_mapping_jabatan);
                $jlh_jabatan_penilai=count($id_jabatan_penilai);
                $jlh_threshold=count($request->threshold);
                $total_mapping=$jlh_mapping+$new_mapping;
                if($jlh_mapping === $jlh_jabatan_penilai && $jlh_mapping === $jlh_threshold){
                    $id_jabatan_peserta=Hashids::decode($request->id_jabatan_peserta);
                    $update=$this->configService->updateMappingJabatan($id_jabatan_peserta[0], $id_mapping_jabatan, $request->active, $request->threshold, $id_jabatan_penilai, $new_mapping);
                    $status=$update['status'];
                    $msg=$update['msg'];
                }else{
                    $msg="Data tidak konsisten ".$total_mapping." ".$jlh_jabatan_penilai." ".$jlh_threshold;
                }
            }catch(\Exception $e){
                $msg=$e->getMessage()." ".$e->getLine();
            }

        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getMappingJabatan($id_jabatan_peserta){
        $data=[];
        $jumlah=0;
        $msg="";
        try{
            $id_jabatan_peserta_dec=Hashids::decode($id_jabatan_peserta);
            if(empty($id_jabatan_peserta)){
                throw new \Exception('Invalid token Id Jabatan Peserta');
            }
            $id_jabatan_peserta_enc=$id_jabatan_peserta_dec[0];
            $get_data_mapping=$this->configService->getMappingJabatanByIdJabatanPeserta($id_jabatan_peserta_enc);
            $data=$get_data_mapping['data'];
            $jumlah=$get_data_mapping['jumlah'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['jumlah'=>$jumlah, 'msg'=>$msg, 'data'=>$data]);
    }


    public function getAllBobot($page){
        if($page < 1){
            $page = 1;
        }
        $get_data=$this->configService->getAllBobot($page);
        $data=$get_data['data'];
        $jumlah_halaman=$get_data['jumlahHalaman'];
        $total=$get_data['total'];
        return response()->json($get_data);
    }

    public function saveNewBobot(Request $request){
        $status=false;
        $data=array();
        try{
            $request->validate([
                'id_jabatan_peserta'=>['required', 'string'],
                'id_jabatan_penilai'=>['required', 'array'],
                'id_jabatan_penilai.*'=>['required', 'string'],
                'bobot'=>['required', 'array'],
                'bobot.*'=>['required', 'integer', 'max:100', 'min:0']
            ]);
            try{
                $jlh_input_penilai=count($request->id_jabatan_penilai);
                $jlh_input_bobot=count($request->bobot);
                $id_jabatan_penilai=[];
                $bobot_penilaian=[];
                if($jlh_input_penilai === $jlh_input_bobot){
                    $id_jabatan_peserta_dec=Hashids::decode($request->id_jabatan_peserta);
                    if(empty($id_jabatan_peserta_dec)){
                        throw new \Exception('Invalid token Id Jabatan Peserta');
                    }
                     $id_jabatan_peserta=$id_jabatan_peserta_dec[0];
                    $bobot=0;
                    for($x=0;$x<$jlh_input_penilai;$x++){
                        $id_jabatan_penilai_dec=Hashids::decode($request->id_jabatan_penilai[$x]);
                        if(empty($id_jabatan_penilai_dec)){
                            throw new \Exception('Invalid token Id Jabatan Penilai');
                        }
                        $id_jabatan_penilai[]=$id_jabatan_penilai_dec[0];
                        $data[]= [
                            'id_jabatan_peserta'=>$id_jabatan_peserta,
                            'id_jabatan_penilai'=>$id_jabatan_penilai_dec[0],
                            'bobot'=>$request->bobot[$x],
                            'active'=>true
                        ];
                        $bobot+=$request->bobot[$x];
                    }
                }else{
                    $msg="Data Peserta, Penilai dan bobot tidak sama";
                }
                if((int)$bobot === 100){
                    $save_bobot=$this->configService->saveBobot($data, $id_jabatan_penilai, $id_jabatan_peserta);
                    $status=$save_bobot['status'];
                    $msg=$save_bobot['msg'];
                }else{
                    $msg="Bobot harus 100%. Current Bobot: ".$bobot;
                }
            }catch(\Exception $e){
                $msg=$e->getMessage()." ".$e->getFile()." ".$e->getLine();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getDetilBobot($id_jabatan_peserta){
        $data=[];
        $msg="";
        $status=false;
        try{
            $id_jabatan_peserta_enc=Hashids::decode($id_jabatan_peserta);
            if(empty($id_jabatan_peserta_enc)){
                throw new \Exception('Invalid token Bobot');
            }
            $id_jabatan_peserta_dec=$id_jabatan_peserta_enc[0];
            $data=$this->configService->getDetilBobot($id_jabatan_peserta_dec);
            $status=$data['status'];
            $msg=$data['msg'];
            $data=$data['data'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function updateBobot(Request $request){
        $update=false;
        try{
            $request->validate([
                'token_id'=>['required', 'array'],
                'token_id.*'=>['string'],
                'id_jabatan_penilai'=>['required', 'array'],
                'id_jabatan_penilai.*'=>['string'],
                'token_peserta'=>['required', 'string'],
                'bobot'=>['required', 'array'],
                'bobot.*'=>['integer', 'min:1', 'max:100']
            ]);
            try{
                $token_peserta_dec=Hashids::decode($request->token_peserta);
                if(empty($token_peserta_dec)){
                    throw new \Exception('Invalid token Peserta');
                }
                $id_jabatan_peserta=$token_peserta_dec[0];

                $jlh_penilai=count($request->token_id);
                $id_bobot_penilai_arr=[];
                $id_jabatan_penilai_arr=[];
                $bobot_arr=[];
                $new_mapping=0;
                $bobot=0;
                for($a=0;$a<$jlh_penilai;$a++){
                    $bobot_arr[]=$request->bobot[$a];
                    if($request->token_id[$a] === "new"){
                        $id_bobot_penilai_arr[]="new";
                        $new_mapping+=1;
                    }else{
                        $token_penilai=Hashids::decode($request->token_id[$a]);
                        if(empty($token_penilai)){
                            throw new \Exception('Invalid token Penilai');
                        }
                        $id_bobot_penilai_arr[]=$token_penilai[0];
                    }
                    
                    $id_jabatan_penilai=Hashids::decode($request->id_jabatan_penilai[$a]);
                    if(empty($id_jabatan_penilai)){
                        throw new \Exception('Invaid token Jabatan Penilai');
                    }
                    $id_jabatan_penilai_arr[]=$id_jabatan_penilai[0];
                    $bobot+=$request->bobot[$a];
                }
                if((int)$bobot === 100 || ((int)$id_jabatan_peserta === 1 && (int)$bobot === 40)){
                    $update_data=$this->configService->updateDataBobot($id_jabatan_peserta, $id_jabatan_penilai_arr, $id_bobot_penilai_arr, $bobot_arr, $new_mapping);
                    $update=$update_data['status'];
                    $msg=$update_data['msg'];
                    // $data_update=$update_data['data_update'];
                }else{
                    $msg="Jumlah Bobot harus 100. Current Bobot: ".$bobot;
                }
            }catch(\Exception $e){
                $msg=$e->getMessage()." ".$e->getLine();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$update, 'msg'=>$msg]);
    }

    public function getListVariable($page){
        if($page < 1){
            $page = 1;
        }
        $get_data=$this->configService->getListVariable($page);
        return response()->json($get_data);
    }

    public function getAllVariable(){
        $get_data=$this->configService->getAllVariable();
        return response()->json($get_data);
    }

    public function saveVariablePertanyaan(Request $request){
        $status=false;
        try{
            $request->validate([
                'variable'=>['required', 'string'],
                'kriteria'=>['required', 'string']
            ]);
            $save_variable=$this->configService->saveVariable($request);
            $status=$save_variable['status'];
            $msg=$save_variable['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getVaribleById($id_variable_enc){
        $data=[];
        $status=false;
        try{
            $id_variable_dec=Hashids::decode($id_variable_enc);
            if(empty($id_variable_dec)){
                throw new \Exception('Invalid token Varible');
            }
            $id_variable=$id_variable_dec[0];
            $get_data=$this->configService->getVariableById($id_variable);
            $status=$get_data['status'];
            $data=$get_data['data'];
            $msg=$get_data['msg'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function updateVariablePertanyaan(Request $request){
        $update=false;
        try{
            $request->validate([
                'token_id_variable'=>['required', 'string'],
                'variable'=>['required', 'string'],
                'kriteria'=>['required', 'string'],
                'status'=>['required', 'max:1', 'in:Y,N']
            ]);
            try{
                $id_variable_dec=Hashids::decode($request->token_id_variable);
                if(empty($id_variable_dec)){
                    throw new \Exception('Invalid token Variabel');
                }
                $id_variable=$id_variable_dec[0];
                $update_data=$this->configService->updateVariablePertanyaan($request, $id_variable);
                $update=$update_data['status'];
                $msg=$update_data['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$update, 'msg'=>$msg]);
    }


    public function getJawabanBundle($page){
        if($page < 1){
            $page = 1;
        }
        $get_data=$this->configService->getJawabanBundle($page);
        $data=$get_data['data'];
        $page=$get_data['page'];
        $jumlah_halaman=$get_data['jumlah_halaman'];
        $total=$get_data['total'];
        return response()->json(['data'=>$get_data]);
    }

    public function getAllBundleJawaban(){
        $get_data=$this->configService->getAllBundleJawaban();
        return response()->json($get_data);
    }

    public function saveJawabanBundle(Request $request){
        $status=false;
        try{
            $request->validate([
                'nama_bundle'=>['required'],
                'jawaban_text'=>['required', 'array'],
                'jawaban_text.*'=>['string'],
                'point'=>['required', 'array'],
                'point.*'=>['integer'],
            ]);
            $jlh_jawaban=count($request->jawaban_text);
            $jlh_point=count($request->point);
            $jawaban_text=[];
            $point=[];
            if($jlh_jawaban === $jlh_point){
                for($x=0;$x<$jlh_jawaban;$x++){
                    $jawaban_text[]=trim($request->jawaban_text[$x]);
                    $point[]=$request->point[$x];
                }
                //check kesamaan
                $continue=true;
                for($x=0;$x<$jlh_jawaban;$x++){
                    $jawaban_sama=0;
                    $point_sama=0;
                    for($y=0;$y<$jlh_jawaban;$y++){
                        if($jawaban_text[$x] === $jawaban_text[$y]){
                            $jawaban_sama++;
                        }
                        if($point[$x] === $point[$y]){
                            $point_sama++;
                        }
                    }
                    if($jawaban_sama > 1){
                        $continue=false;
                        break;
                    }
                    if($point_sama > 1){
                        $continue = false;
                        break;
                    }
                    
                }

                if($continue === true){
                    $bundle_code=substr(md5(microtime(true)), 0, 5);
                    $data_insert=[];
                    for($a=0;$a<$jlh_jawaban;$a++){
                        $data_insert[]=[
                            'bundle_code'=>$bundle_code,
                            'bundle_name'=>trim($request->nama_bundle),
                            'jawaban_text'=>$jawaban_text[$a],
                            'point_jawaban'=>$request->point[$a],
                        ];
                    }
                    $save_jawaban=$this->configService->saveJawabanBundle($data_insert, trim($request->nama_bundle));
                    $status=$save_jawaban['status'];
                    $msg=$save_jawaban['msg'];
                }else{
                    $msg="Ada Text Jawaban atau Point yang sama. Silahkan diganti salah satu ";
                }
            }else{
                $msg="Data tidak konsisten";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getJawabanBundleDetil($bundle_code){
        $get_data=$this->configService->getJawabanBundleDetil($bundle_code);
        return response()->json($get_data);
    }

    public function updateBundleJawaban(Request $request){
        $status=false;
        try{
            $request->validate([
                'bundle_name'=>['required'],
                'jawaban_text'=>['required', 'array'],
                'jawaban_text.*'=>['string', 'min:4'],
                'point'=> ['required', 'array'],
                'point.*'=> ['integer'],
                'token_bundle'=>['required', 'array'],
                'token_bundle.*'=>['string'],
                'bundle_code'=>['required', 'string'],
                'payload'=>['required', 'string']
            ]);
            try{
                $jawaban_text=[];
                $point=[];
                $id_bundle=[];
                $jlh_jawaban_text=count($request->jawaban_text);
                $jlh_point=count($request->point);
                $jlh_token_bundle=count($request->token_bundle);
                $new_bundle=0;
                if($jlh_jawaban_text === $jlh_point && $jlh_point === $jlh_token_bundle){
                    for($x=0;$x<$jlh_jawaban_text;$x++){
                        $jawaban_text[]=$request->jawaban_text[$x];
                        $point[]=$request->point[$x];
                        if($request->token_bundle[$x] === "new"){
                            $new_bundle++;
                            $id_bundle[]=$request->token_bundle[$x];
                        }else{
                            $id_bundle_dec=Hashids::decode($request->token_bundle[$x]);
                            if(empty($id_bundle_dec)){
                                throw new \Exception('Invalid token Bundle Jawaban');
                            }
                            $id_bundle[]=$id_bundle_dec[0];
                        }
                    }
                    
                    $continue=true;
                    $sama_jawaban=0;
                    $sama_point=0;
                    for($a=0;$a<$jlh_jawaban_text;$a++){
                        for($b=0;$b<$jlh_jawaban_text;$b++){
                            if($jawaban_text[$a] === $jawaban_text[$b]){
                                $sama_jawaban++;
                            }
                            if($point[$a] === $point[$b]){
                                $sama_point++;
                            }
                        }
                    }

                    if($sama_jawaban > 1 || $sama_point > 1){
                        $update_bundle_jawaban=$this->configService->updateBundleJawaban($request->bundle_code, $request->bundle_name, $id_bundle, $jawaban_text, $point, $new_bundle);
                        $status=$update_bundle_jawaban['status'];
                        $msg=$update_bundle_jawaban['msg'];
                    }else{
                        $msg="Data Jawaban dan Point ada yang sama";
                    }
                }else{
                    $msg="Data Jawaban, Point dan Token Bundle tidak Konsisten";
                }
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getListPertanyaan($page){
        if($page < 1){
            $page = 1;
        }
        $get_data=$this->configService->getListPertanyaan($page);
        return response()->json($get_data);
    }

    public function savePertanyaan(Request $request){
        $status=false;
        try{
            $request->validate([
                'id_variable'=>['required', 'string'],
                'pertanyaan'=>['required', 'string'],
                'bundle_code_jawaban'=>['required', 'string'],
                'bobot'=> ['required', 'integer', 'max:100'],
            ]);
            $clean=trim($request->pertanyaan);
            $pertanyaan=strip_tags($clean);
            try{
                // $bundle_code_dec=Hashids::decode($request->bundle_code_jawaban); <- ga di encode
                $id_variable_dec=Hashids::decode($request->id_variable);
                if(empty($id_variable_dec)){
                    throw new \Exception('Invalid Token Variable');
                }
                $id_variable=$id_variable_dec[0];
                $save_pertanyaan=$this->configService->savePertanyaan($id_variable, $pertanyaan, $request->bundle_code_jawaban, $request->bobot);
                $status=$save_pertanyaan['status'];
                $msg=$save_pertanyaan['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }

        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getPertanyaanDetil($id_pertanyaan){
        $status=false;
        $signature="";
        $data=[];
        $msg="";
        try{
            $id_pertanyaan_enc=Hashids::decode($id_pertanyaan);
            if(empty($id_pertanyaan_enc)){
                throw new \Exception('Invalid Token Id Pertanyaan');
            }
            $id_pertanyaan_=$id_pertanyaan_enc[0];
            $get_data=$this->configService->getPertanyaanDetil($id_pertanyaan_);
            $status=$get_data['status'];
            $msg=$get_data['msg'];
            $data=$get_data['data'];
            $signature=$get_data['signature'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }
        
        return response()->json(['status'=>$status, 'msg'=>$msg, 'signature'=>$signature, 'data'=>$data]);
    }

    public function updatePertanyaan(Request $request){
        $status=false;
        $msg="";
        try{
            $request->validate([    
                'token_pertanyaan'=>['required', 'string'],
                'token_variable'=>['required', 'string'],
                'pertanyaan'=>['required'],
                'bundle_code_jawaban'=>['required', 'string'],
                'bobot'=>['required', 'integer', 'max:100'],
                'active'=>['required', 'max:1', 'in:Y,N'],
                'payload'=>['required', 'string']
            ]);
            $token_variable=Hashids::decode($request->token_variable);
            $token_pertanyaan=Hashids::decode($request->token_pertanyaan);
            if(empty($token_variable) || empty($token_pertanyaan)){
                throw new \Exception('Invalid token variable atau token pertanyaan');
            }
            $id_variable=$token_variable[0];
            $id_pertanyaan=$token_pertanyaan[0];
            $clean=trim($request->pertanyaan);
            $pertanyaan=strip_tags($clean);
            $update_pertanyaan=$this->configService->updatePertanyaan($id_pertanyaan, $id_variable, $pertanyaan, $request->active, $request->bobot, $request->bundle_code_jawaban);
            $status=$update_pertanyaan['status'];
            $msg=$update_pertanyaan['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

}
