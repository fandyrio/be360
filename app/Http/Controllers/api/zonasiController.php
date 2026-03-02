<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Jobs;
use App\Models\Log_msg;
use Illuminate\Http\Request;
use App\Services\zonasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;
use App\Models\Satker;
use App\Models\Trans_jabatan_kosong;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class zonasiController extends Controller
{
    protected $zonasiService;

    public function __construct(zonasiService $zonasi_service){
        $this->zonasiService=$zonasi_service;
    }

    public function getListZonasi($page = null): JsonResponse{
        $get_data=$this->zonasiService->listZonasi($page);
        return response()->json($get_data);
    }

    public function saveZonasi(Request $request): JsonResponse{
        $status=false;
        $access=true;
        try{
            $request->validate([
                'nama_zona'=> ['required', 'string'],
                'start_date'=> ['required', 'date'],
                'end_date'=> ['required', 'date'],
                'id_tahun_penilaian'=> ['required', 'string'],
                'is_active'=> ['required', 'size:1', 'in:Y,N'],
                'id_satker'=>['required', 'array'],
            ]);

            try{
                $id_tahun_penilaian=Hashids::decode($request->id_tahun_penilaian);
                
                if(empty($id_tahun_penilaian)){
                    throw new \Exception('Invalid token');
                }

                if($request->start_date > $request->end_date){
                    $access=false;
                }
                if($access === true){
                    $save=$this->zonasiService->saveZonasi($request, $id_tahun_penilaian[0]);
                    $status=$save['status'];
                    $msg=$save['msg'];
                }else{
                    $msg="Start date harus lebih awal dari End date";
                }
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }

        }catch(ValidationException $e){
            $msg="Error Validation: ".$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function regeneratePeserta(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_zonasi'=>['required', 'string'],
                'payload'=>['required', 'string']
            ]);
            try{
                 $id_zonasi=Hashids::decode($request->token_zonasi);
                if(empty($id_zonasi)){
                    throw new \Exception('Ivalid Token Zonasi');
                }
                $regenerate=$this->zonasiService->regeneratePeserta($id_zonasi[0]);
                $status=$regenerate['status'];
                $msg=$regenerate['msg'];
            }catch(\Exception $e){
                Log::error($e->getMessage());
                $msg=$e->getMessage()." ".$e->getFile()." ".$e->getLine();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getPesertaSIKEPByPeriode($id_tahun_penilaian){
        // return $this->zonasiService->getPesertaSIKEPByPeriode($id_tahun_penilaian);
    }

    public function getZonasiById($id){
        $status=false;
        $data=[];
        $msg="";
        $view=null;
        try{
            $dec_id=Hashids::decode($id);
            if(empty($dec_id)){
                throw new \Exception('Invalid Token');
            }
            $get_data=$this->zonasiService->getZoneById($dec_id[0]);
            $status=$get_data['status'];
            $msg=$get_data['msg'];
            $data=$get_data['data'];
            $signature=$get_data['signature'];
            $view=$get_data['view'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }
        
        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data, 'signature'=>$signature, 'view'=>$view]);
    }

    public function getSatkerZonasi($id_zonasi_enc){
        $status=false;
        $data=[];
        $msg="";
        $regenerate=null;
        $run_job=null;
        try{
            $dec_id=Hashids::decode($id_zonasi_enc);
            if(empty($dec_id)){
                throw new \Exception('Invalid token');
            }
            $get_data=$this->zonasiService->getSatkerZonasi($dec_id[0]);
            $status=$get_data['status'];
            $data=$get_data['data'];
            $msg=$get_data['msg'];
            $regenerate=$get_data['regenerate'];
            $run_job=$get_data['run_job'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'run_job'=>$run_job, 'regenerate'=>$regenerate, 'data'=>$data]);
    }

    public function getAllSatkerAll(){
        $get_data=$this->zonasiService->getDataSatkerLengkap();
        return response()->json($get_data);
    }

    public function observer($id_zonasi){
        $get_data=$this->zonasiService->getPeserta($id_zonasi);
        var_dump($get_data);
        // return view('list_observe', ['data'=>$get_data]);
        // return response()->json($get_data);
    }

    public function addSatkerToZonasi(Request $request){
        $status=false;
        try{
            $request->validate([
                'id_satker'=>['required', 'array'],
                'id_zonasi'=>['required', 'string']
            ]);
            try{
                $id_zonasi_dec=Hashids::decode($request->id_zonasi);
                if(empty($id_zonasi_dec)){
                    throw new \Exception('Invalid token');
                }
                $id_zonasi=$id_zonasi_dec[0];
                $add_satker=$this->zonasiService->addSatkerToZonasi($request, $id_zonasi);
                $status=$add_satker['status'];
                $msg=$add_satker['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function removeExistedSatkerZonasi(Request $request){
        $status=false;
        try{
            $request->validate([
                'id_satker'=>['required', 'array'],
                'id_zonasi'=>['required', 'string']
            ]);
            try{
                $id_zonasi=Hashids::decode($request->id_zonasi);
                if(empty($id_zonasi)){
                    throw new \Exception('Invalid token');
                }
                $remove_satker=$this->zonasiService->removeExistedSatker($request, $id_zonasi);
                $status=$remove_satker['status'];
                $msg=$remove_satker['msg'];
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            // $msg=$e->getMessage();
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function enc($method, $string){
        if($method === "enc"){
            $result=Hashids::encode($string);
        }elseif($method === "dec"){
            $dec=Hashids::decode($string);
            $result=$dec[0];
        }
        echo $result;
    }

    public function getProgressLog($id_zonasi){
        $status=false;
        try{
            $id_zonasi_dec=Hashids::decode($id_zonasi);
            if(empty($id_zonasi_dec)){
                throw new \Exception('Invalid token zonasi');
            }
            $get_progress=$this->zonasiService->countProgress($id_zonasi_dec[0]);
            $status=$get_progress['status'];
            $msg=$get_progress['msg'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function runQueue(Request $request){
        // abort_unless($key === env('QUEUE_KEY', '123secret'), 403);
        $status=false;
        try{
            $request->validate([
                'token_zonasi'=>['required', 'string'],
                'payload'=>['required', 'string']
            ]);
             try{
                $id_zonasi=Hashids::decode($request->token_zonasi);
                if(empty($id_zonasi)){
                    throw new \Exception('Invalid Token Zonasi');
                }
                $get_entry_job=$this->zonasiService->checkEntryJobTransZonasiSatker($id_zonasi[0]);
                $jlh_entry_job_false=$get_entry_job['entry_job_false'];
                $status=$get_entry_job['status'];
                $msg=$get_entry_job['msg'];
                if($status === true){
                    $status=false;
                    $jlh_jobs=Jobs::where('queue', 'insert_data_peserta_'.$id_zonasi[0])->count();
                    if($jlh_jobs > 0){
                        if($jlh_entry_job_false === 0){
                            Artisan::call("queue:work --queue=insert_data_peserta_".$id_zonasi[0]." --stop-when-empty");
                            $status=true;
                            $msg = 'Queue sedang dijalankan...';
                        }else{
                            $msg="Ada Satker yang belum di generate Pesertanya. Silahkan regenerate Data Peserta terlebih dahulu";
                        }
                    }else{
                        $msg="Tidak ada Antrian Data";
                    }
                }
            }catch(\Exception $e){
                $msg=$e->getMessage();
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function getJabatanKosong($page, $id_zonasi){
        $status=false;
        $jumlah=0;
        $data=null;
        $msg="";
        $total=0;
        $jumlah_halaman=0;
        $no=0;
        try{
            $id_zonasi_dec=Hashids::decode($id_zonasi);
            if(empty($id_zonasi)){
                throw new \Exception('Invalid token Zonasi');
            }
           $get_jabatan_kosong=$this->zonasiService->getJabatanKosong($page, $id_zonasi_dec[0]);
           $status=$get_jabatan_kosong['status'];
           $data=$get_jabatan_kosong['data'];
           $total=$get_jabatan_kosong['total'];
           $jumlah_halaman=$get_jabatan_kosong['jumlah_halaman'];
           $page=$get_jabatan_kosong['page'];
           $msg=$get_jabatan_kosong['msg'];
           $no=(int)$get_jabatan_kosong['no'];
        }catch(\Exception $e){
            $msg=$e->getMessage(). " ".$e->getFile();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'total'=>$total, 'page'=>$page, 'jumlah_halaman'=>$jumlah_halaman, 'no'=>$no, 'data'=>$data]);
    }
    public function getPesertaZonasiSatker($id_zonasi_enc){
        $status=false;
        $data=[];
        try{
            $zonasi_id=Hashids::decode($id_zonasi_enc);
            if(empty($zonasi_id)){
                throw new \Exception('Invalid Token Zonasi');
            }
            $get_peserta=$this->zonasiService->getPesertaZonasiSatker($zonasi_id[0]);
            $status=$get_peserta['status'];
            $msg=$get_peserta['msg'];
            $data=$get_peserta['data'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function getKPT($id){
        return $this->zonasiService->getKPT($id);
    }

    public function sendNotifJabatanKosong($id_zonasi){
        $send_wa=$this->zonasiService->sendNotifJabatanKosong($id_zonasi);
        $status=$send_wa['status'];
        $msg=$send_wa['msg'];
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function monitoringBadilum(Request $request, $id_zonasi_enc, $page, $refresh=null){
        $data=[];
        $status=false;
        if($page < 1){
            $page = 1;
        }
        try{
            $id_satker=$request->user()->IdSatker;
            $id_zonasi=Hashids::decode($id_zonasi_enc);
            if(empty($id_zonasi)){
                throw new \Exception("Data Monitoring tidak ditemukan");
            }
            $id_zonasi=$id_zonasi[0];
            if(!is_null($refresh) && $refresh === "true"){
                Cache::store('redis')->forget("monitoring_badilum_{$id_zonasi}");
            }
            $monitoring=$this->zonasiService->monitoringBadilum($id_zonasi, $page);
            $status=$monitoring['status'];
            $msg=$monitoring['msg'];
            $data=$monitoring['data'];
            $page=$monitoring['page'];
            $jumlah_halaman=$monitoring['jumlah_halaman'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return [
            'status'=>$status,
            'msg'=>$msg,
            'page'=>$page,
            'jumlah_halaman'=>$jumlah_halaman,
            'data'=>$data
        ];
    }

    public function generatePesertaTest($id_zonasi_satker)  {
        return $this->zonasiService->generatePesertaTest($id_zonasi_satker);
    }
}
