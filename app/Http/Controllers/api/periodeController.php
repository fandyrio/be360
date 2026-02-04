<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Tahun_penilaian;
use Illuminate\Http\Request;
use App\Services\periodeService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationData;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

class periodeController extends Controller
{
    protected $periodeService;
    public function __construct(periodeService $periode_service)
    {
        $this->periodeService=$periode_service;
    }

    public function listPeriode($page=null){
        $get_data=$this->periodeService->getListPeriode($page);
        return response()->json($get_data);
    }

    public function savePeriode(Request $request){
        $status=false;
        try{
            $current_year=date("Y");
            $max_year=$current_year+2;
            $request->validate([
                'tahun'=>['required', 'numeric', 'between:'. $current_year.",".$max_year],
                'dasar_hukum'=>['required', 'file', 'mimes:pdf'],
                'keterangan'=>['nullable', 'string']
            ]);
            $edoc=$request->dasar_hukum;
            $size=$edoc->getSize();
            $type=$edoc->getMimeType();
            if($size <= 5000000 && $type === "application/pdf"){
                $destination="edoc";
                $filename=date('YmdHis').str_replace(" ", "_", $edoc->getClientOriginalName());
                $path=$edoc->storeAs($destination, $filename, 'public');
                if(Storage::disk('public')->exists($path)){
                   $save_data=$this->periodeService->savePeriode($request, $path);
                   $status=$save_data['status'];
                   $msg=$save_data['msg'];
                }else{
                    $msg="File tidak dapat disimpan. Penyimpanan data tidak dapat dilanjutkan";
                }
            }else{
                $msg="Ukuran file harus dibawah 5MB dan Tipe file harus PDF";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }
    public function getPeriodeById($id){
        $status=false;
        $data=null;
        $msg="";
        try{
            $id_periode=Hashids::decode($id);
            if(empty($id_periode)){
                throw new \Exception('Invalid token');
            }
            $get_data=$this->periodeService->getPeriodeById($id_periode);
            $data=$get_data['data'];
            $msg=$get_data['msg'];
            $status=$get_data['status'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function updatePeriode(Request $request){
        $status=false;
        $msg="";
        $path=null;
        try{
            $current_year=date("Y");
            $max_year=$current_year+2;
            $request->validate([
                'dasar_hukum'=> ['nullable', 'file', 'mimes:pdf'],
                'keterangan'=>['nullable', 'strring'],
                'tahun'=>['required', 'numeric', 'between:'. $current_year.",".$max_year],
            ]);
             try{
                $id_periode=Hashids::decode($request->enc_id);
                if(empty($id_periode)){
                    throw new \Exception('Invalid token');
                }
                $file=$request->file('dasar_hukum');
                $upload=true;
                if($file !== "" && !is_null($file)){
                    $upload=false;
                    $size=$file->getSize();
                    $type=$file->getMimeType();
                    if($size <= 5000000 && $type === "application/pdf"){
                        $destination="edoc";
                        $filename=date('YmdHis')."".str_replace(" ", "", $file->getClientOriginalName());
                        $path=$file->storeAs($destination, $filename, 'public');
                        if(Storage::disk('public')->exists($path)){
                            $upload=true;
                        }else{
                            $msg="Upload eDoc tidak dapat dilakuikan";
                        }
                    }else{
                        $msg="Tipe File harus PDF dan size harus dibawah 5Mb Current".$type." : ".$size;
                    }
                }
                if($upload){
                    $update=$this->periodeService->updatePeriode($request, $id_periode, $path);
                    $status=$update['status'];
                    $msg=$update['msg'];
                }
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function deletePeriode(Request $request){
        $status=false;
        try{
            $id=Hashids::decode($request->enc_id);
            if(empty($id)){
                throw new \Exception('Invalid token');
            }
            $delete=$this->periodeService->deletePeriode($id);
            $status=$delete['status'];
            $msg=$delete['msg'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }
    
    public function listActivePeriode(){
        $get_data=$this->periodeService->listActivePeriode();
        return response()->json($get_data);
    }

    public function getBobotPenilaianPeriode($id_periode){
        $data=[];
        $signature="";
        $status=false;
        try{
            $id_periode_dec=Hashids::decode($id_periode);
            if(empty($id_periode_dec)){
                throw new \Exception('Invalid token Periode');
            }
            $id_periode_=$id_periode_dec[0];
            $get_bobot=$this->periodeService->getBobotPenilaianPeriode($id_periode_);
            $data=$get_bobot['data'];
            $status=$get_bobot['status'];
            $msg=$get_bobot['msg'];
            $signature=$get_bobot['signature'];
            $token_periode=$id_periode;
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'token_periode'=>$token_periode, 'signature'=>$signature, 'data'=>$data]);
    }

    public function removeBobotPenilaianPeriode(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_trans_bobot'=>['required', 'string'],
                'token_periode'=>['required', 'string'],
                // 'token_payload'=>['required', 'string'],
                'payload'=>['required']
            ]);
            try{
                $id_trans_bobot_dec=Hashids::decode($request->token_trans_bobot);
                $id_periode_dec=Hashids::decode($request->token_periode);
                if(empty($id_trans_bobot_dec) || empty($id_periode_dec)){
                    throw new \Exception('Invalid token Bobot atau Periode');
                }
                
                $id_trans_bobot=$id_trans_bobot_dec[0];
                $id_periode=$id_periode_dec[0];
                $remove=$this->periodeService->removeBobotPenilaianPeriode($id_trans_bobot, $id_periode);
                $status=$remove['status'];
                $msg=$remove['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function regenerateBobotPenilaianPeriode(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_periode'=>['required', 'string'],
                'payload'=>['required']
            ]);
            try{
                $id_periode=Hashids::decode($request->token_periode);
                if(empty($id_periode)){
                    throw new \Exception('Invalid token Periode');
                }
                $regenerate=$this->periodeService->regenerateBobotPenilaian($id_periode[0]);
                $status=$regenerate['status'];
                $msg=$regenerate['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getPertanyaanPeriode($id_periode){
        $data=[];
        $status=false;
        $msg="";
        $signature="";
        try{
            $id_periode_dec=Hashids::decode($id_periode);
            if(empty($id_periode_dec)){
                throw new \Exception('Invalid token');
            }
            $get_pertanyaan=$this->periodeService->getPertanyaanPeriode($id_periode_dec[0]);
            $data=$get_pertanyaan['data'];
            $status=$get_pertanyaan['status'];
            $msg=$get_pertanyaan['msg'];
            $signature=$get_pertanyaan['signature'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'signature'=>$signature, 'data'=>$data]);
    }

    public function removePertanyaanPeriode(Request $request){
        $status=false;
        $msg="";
        try{
            $request->validate([
                'token_periode'=>['required', 'string'],
                'token_trans_pertanyaan'=>['required', 'string'],
                'payload'=>['required', 'string']
            ]);
            try{
                $id_periode=Hashids::decode($request->token_periode);
                $id_trans_pertanyaan=Hashids::decode($request->token_trans_pertanyaan);
                if(empty($id_periode) || empty($id_trans_pertanyaan)){
                    throw new \Exception('Invalid token Periode atau Trans Pertanyaan');
                }
                $remove=$this->periodeService->removePertanyaanPeriode($id_trans_pertanyaan[0], $id_periode[0]);
                $status=$remove['status'];
                $msg=$remove['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function regeneratePertanyaanPeriode(Request $request){
        $status=false;
        $msg="";
        try{
            $request->validate([
                'token_periode'=>['required', 'string'],
                'payload'=>['required', 'string']
            ]);
            try{
                $id_periode=Hashids::decode($request->token_periode);
                if(empty($id_periode)){
                    throw new \Exception('Invalid token Periode');
                }
                $regenerate=$this->periodeService->regeneratePertanyaanPeriode($id_periode[0]);
                $status=$regenerate['status'];
                $msg=$regenerate['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getMappingJabatanPeriode($id_periode){
        $data=[];
        $status=false;
        $jumlah=0;
        $msg="";
        $signature="";
        try{
            $id_periode_dec=Hashids::decode($id_periode);
            if(empty($id_periode_dec)){
                throw new \Exception('Invalid Token Periode');
            }
            $get_data=$this->periodeService->getMappingJabatanPeriode($id_periode_dec[0]);
            $data=$get_data['data'];
            $status=$get_data['status'];
            $msg=$get_data['msg'];
            $jumlah=$get_data['jumlah'];
            $signature=$get_data['signarture'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'signature'=>$signature, 'jumlah'=>$jumlah, 'data'=>$data]);
    }

    public function removeMappingJabatanPeriode(Request $request){
        $status=false;
        $msg="";
        try{
            $request->validate([
                'token_trans_mapping'=>['required', 'string'],
                'token_periode'=>['required', 'string'],
                'payload'=>['required']
            ]);
            $id_trans_mapping=Hashids::decode($request->token_trans_mapping);
            $id_periode=Hashids::decode($request->token_periode);
            if(empty($id_trans_mapping) || empty($id_periode)){
                throw new \Exception('Invalid token Trans Mapping or Periode Token');
            }
            $remove_data=$this->periodeService->removeMappingJabatanPeriode($id_trans_mapping[0], $id_periode[0]);
            $status=$remove_data['status'];
            $msg=$remove_data['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function regenerateMappingJabatanPeriode(Request $request){
      $status=false;
      $msg="";
      try{
        $request->validate([
            'token_periode'=>['required', 'string'],
            'payload'=>['required', 'string']
        ]);
        $id_periode=Hashids::decode($request->token_periode);
        if(empty($id_periode)){
            throw new \Exception('Invalid token Periode');
        }
        $regenerate=$this->periodeService->regenerateMappingJabatanPeriode($id_periode[0]);
        $status=$regenerate['status'];
        $msg=$regenerate['msg'];
      }catch(ValidationException $e){
        $msg=$e->validator->errors()->first();
      }

      return response()->json(['status'=>$status, 'msg'=>$msg]);
    }


}
