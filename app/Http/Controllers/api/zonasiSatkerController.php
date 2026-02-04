<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Jobs;
use App\Models\Log_msg;
use App\Models\Tref_sys_config;
use Illuminate\Http\Request;
use App\Services\zonasiSatkerService;
use DateTime;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\Fluent\Concerns\Has;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

class zonasiSatkerController extends Controller
{
     protected $zonasiSatkerService;

    public function __construct(zonasiSatkerService $zonasi_satker_service, Request $request){
        $this->zonasiSatkerService=$zonasi_satker_service;

        if(!checkDataAdminSatker($request->user()->uname)){
            return response()->json(['status'=>false, 'msg'=>'Silahkan Melengkapi data Admin Terlebih dahulu']);        
        }
    }

    public function listZonasiSatker($page, Request $request){
        $id_satker=$request->user()->IdSatker;
        
        $get_data=$this->zonasiSatkerService->listZonasiSatker($page, $id_satker);
        $status=$get_data['status'];
        $msg=$get_data['msg'];
        $jumlah_halaman=$get_data['jumlah_halaman'];
        $total=$get_data['total'];
        $page=(int)$get_data['page'];
        $data=$get_data['data'];

        return response()->json(['status'=>$status, 'msg'=>$msg, 'jumlah_halaman'=>$jumlah_halaman, 'total'=>$total, 'page'=>$page, 'data'=>$data]);
    }

    
    public function detilZonasiSatker($id_zonasi_satker_enc, Request $request){
        $status=false;
        $data=[];
        $signature="";
        $msg="";
        $id_satker=0;
        $view=null;
        if(isset($request->user()->IdSatker)){
            $id_satker=$request->user()->IdSatker;
        }
        try{
            $id_zonasi_satker=Hashids::decode($id_zonasi_satker_enc);
            if(empty($id_zonasi_satker)){
                throw new \Exception('Invalid token zonasi satker');
            }
            $get_detil_zonasi=$this->zonasiSatkerService->detilZonasiSatker($id_zonasi_satker[0], $id_satker);
            $status=$get_detil_zonasi['status'];
            $msg=$get_detil_zonasi['msg'];
            $signature=$get_detil_zonasi['signature'];
            $data=$get_detil_zonasi['data'];
            $view=$get_detil_zonasi['view'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'signature'=>$signature, 'data'=>$data, 'view'=>$view]);
    }

    public function getJabatanKosongSatker($id_zonasi_satker_enc, Request $request){
        $status=false;
        $data=[];
        $msg="";
        $id_satker=0;
        if(isset($request->user()->IdSatker)){
            $id_satker=$request->user()->IdSatker;
        }
        try{
            $id_zonasi_satker=Hashids::decode($id_zonasi_satker_enc);
            if(empty($id_zonasi_satker)){
                throw new \Exception("Invalid token Zonasi Satker");
            }
            $get_data=$this->zonasiSatkerService->getJabatanKosongSatker($id_zonasi_satker, $id_satker);
            $status=$get_data['status'];
            $msg=$get_data['msg'];
            $send_confirm=$get_data['send_confirm'];
            $data=$get_data['data'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'send_confirm'=>$send_confirm, 'data'=>$data]);
    }

    public function detilJabatanKosongSatker($token_jabatan_kosong, Request $request){
        $status=false;
        $data=null;
        $msg="";
        $signature=null;
        $id_satker=0;
        if(isset($request->user()->IdSatker)){
            $id_satker=$request->user()->IdSatker;
        }
        try{
            $id_jabatan_kosong=Hashids::decode($token_jabatan_kosong);
            if(empty($id_jabatan_kosong)){
                throw new \Exception('Invalid token');
            }
            $get_data=$this->zonasiSatkerService->detilJabatanKosongSatker($id_jabatan_kosong[0], $id_satker);
            $status=$get_data['status'];
            $msg=$get_data['msg'];
            $signature=$get_data['signature'];
            $data=$get_data['data'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'signature'=>$signature, 'data'=>$data]);
        
    }

    public function getPegawaiLocalByNIP(Request $request){
        $status=false;
        $data=[];
        try{
            $request->validate([
                'nip'=>['required', 'string', 'size:18'],
                'token_jabatan_kosong'=>['required', 'string']
            ]);

            $id_jabatan_kosong=Hashids::decode($request->token_jabatan_kosong);
            if(empty($id_jabatan_kosong)){
                return response()->json(['status'=>false, 'msg'=>'Invalid data jabatan kosong']);
            }

            $id_satker=0;
            if(isset($request->user()->IdSatker)){
                $id_satker=$request->user()->IdSatker;
            }
            $get_pegawai=$this->zonasiSatkerService->getDataPegawaiLocalByNIP($request->nip, $id_satker, $id_jabatan_kosong[0]);
            $status=$get_pegawai['status'];
            $msg=$get_pegawai['msg'];
            $data=$get_pegawai['data'];

        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }

    public function saveJabatanKosongSatker(Request $request){
        $status=false;
        try{
            $request->validate([
                'nip'=>['required', 'string', 'size:18'],
                'token_jabatan_kosong'=>['required', 'string'],
                'payload'=>['required']
            ]);
            $id_jabatan_kosong=Hashids::decode($request->token_jabatan_kosong);
            if(empty($id_jabatan_kosong)){
                return response()->json(['status'=>false, 'msg'=>'Invalid data Token Jabatan']);
            }
            $id_satker=0;
            if(isset($request->user()->IdSatker)){
                $id_satker=$request->user()->IdSatker;
            }
            $save_jabatan_kosong=$this->zonasiSatkerService->saveJabatanKosongSatker($request->nip, $id_satker, $id_jabatan_kosong);
            $status=$save_jabatan_kosong['status'];
            $msg=$save_jabatan_kosong['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function sendConfirmJabatanKosong(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_zonasi_satker'=> ['required', 'string'],
                'payload'=>['required']
            ]);
            $id_zonasi_satker=Hashids::decode($request->token_zonasi_satker);
            if(empty($id_zonasi_satker)){
                return response()->json(['status'=>false, 'msg'=>"Invalid token Zonasi Satker"]);
            }

            $send=$this->zonasiSatkerService->sendConfirmJabatanKosong($id_zonasi_satker[0], $request->user()->uname);
            $status=$send['status'];
            $msg=$send['msg'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function sendNotificationPeserta(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_zonasi'=>['required', 'string'],
                'payload'=> ['required']
            ]);
            $id_zonasi=Hashids::decode($request->token_zonasi);
            if(empty($id_zonasi)){
                return response()->json(['status'=>false, 'msg'=>"Data Token Zonasi tidak valid"]);
            }

            $check_data=$this->zonasiSatkerService->checkJabatanKosongZonasi($id_zonasi[0]);
            if((int)$check_data === 0){
                $jumlah_jobs=Jobs::where('queue', "send_wa_peserta_".$id_zonasi[0])->count();
                if($jumlah_jobs > 0){

                    //check apakah jobs sedang running
                    $get_log=Log_msg::where('category', 'jobs_notif')
                                    ->where('status', 'progress')
                                    ->where('data_id', $id_zonasi[0])
                                    ->first();
                    if(!is_null($get_log)){
                        return response()->json(['status'=>false, 'msg'=>"Pengiriman Pesan Sedang berjalan. Mohon menunggu ..."]);
                    }

                    $config=Tref_sys_config::where('config_name', 'msg_per_minutes')->first();
                    $msg_per_minutes=(int)$config['config_value_str'];
                    $jlh_menit=$jumlah_jobs / $msg_per_minutes;
                    $detik=$jlh_menit * 60;
                    $max_time=$detik + 60;

                    //Convert Minutes to Hours and Minutes
                    if($jlh_menit >= 60){
                        $jlh_jam=floor($jlh_menit / 60);
                        $jlh_menit=$jlh_menit % 60;
                        if($jlh_menit === 0){
                            $time_display=$jlh_jam." Jam ";
                        }else{
                            $time_display=$jlh_jam." Jam ".$jlh_menit." menit";
                        }
                    }else{
                        $time_display=$jlh_menit." menit";
                    }
                    $date_now=new DateTime(date('Y-m-d H:i:s'));
                    $date_now->modify("+ ".$jlh_menit." minutes");

                    exec("php ".base_path('artisan'). " queue:work --queue=send_wa_peserta_".$id_zonasi[0]." --sleep=10 --tries=3 --timeout=120 --max-time=".$max_time." > /dev/null 2>%1 &");
                    // Artisan::call("queue:work --queue=send_wa_peserta  --sleep=10 --tries=3 --timeout=120 --max-time={$max_time}");
                    $status=true;
                    $msg="Queue Send Peserta sedang berjalan. Akan memakan waktu ".$time_display;
                    $msg.="\nPerkiraan Selesai Pada ".$date_now->format("d M Y H:i"). " Wib";
                }else{
                    $msg="Tidak ada Jobs yang ditemukan";
                }
            }else{
                $msg="Masih ada data Jabatan Kosong yang belum terisi. Silahkan diisi terlebih dahulu";
            }

        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function progressJobsNotif($id_zonasi){
        $status=false;
        try{
            $id_zonasi=Hashids::decode($id_zonasi);
            if(empty($id_zonasi)){
                throw new \Exception('Invalid token Zonasi Satker');
            }
            $get_progress=$this->zonasiSatkerService->progressJobsNotif($id_zonasi[0]);
            $status=$get_progress['status'];
            $msg=$get_progress['msg'];
        }catch(\Exception $e){
            $msg=$e->getMessage()." ".$e->getLine()." ".$e->getFile();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    public function montoringZonasiSatker(Request $request, $id_zonasi_satker_enc, $page, $refresh=null){
        $status=false;
        $jumlah_halaman=0;
        $percentage=0;
        $ration="0/0";
        $jumlah_halaman=0;
        $data=[];
        $send_to_badilum=false;
        try{
            $id_zonasi=Hashids::decode($id_zonasi_satker_enc);
            if(empty($id_zonasi)){
                throw new \Exception("Data zonasi tidak ditemukan");
            }
            $id_satker=0;
            if(isset($request->user()->IdSatker)){
                $id_satker=$request->user()->IdSatker;
            }
            if($page < 0){
                $page = 1;
            }
            $limit=50;
            if(is_null($refresh)){
                $refresh=false;
            }else{
                $refresh=true;
            }
            $get_data=$this->zonasiSatkerService->monitoringZonasiSatker($id_zonasi[0], $id_satker, $limit, $page, $refresh);
            $status=$get_data['status'];
            $msg=$get_data['msg'];
            $jumlah_halaman=$get_data['jumlahHalaman'];
            $percentage=$get_data['percentage'];
            $data=$get_data['data'];
            $send_to_badilum=$get_data['send_to_badilum'];
            // $send_to_badilum=true;
            $sudah_menilai=$get_data['sudah_menilai'];
            $total_penilaian=$get_data['total_penilaian'];
            $signature=$get_data['signature'];
            $token_monitoring=$get_data['token_monitoring'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg, 'send_to_badilum'=>$send_to_badilum, 'jumlah_halaman'=>$jumlah_halaman, 'page'=>$page, 'percentage'=>$percentage, 'sudah_menilai'=>$sudah_menilai, 'total_penilaian'=>$total_penilaian, 'token_monitoring'=>$token_monitoring, 'signature'=>$signature, 'data'=>$data]);
    }

    public function sendPenilaianToBadilum(Request $request){
        $status=false;
        try{
            $request->validate([
                'token_monitoring'=>['required', 'string'],
                'payload'=>['required', 'string']
            ]);
            $explode_token=explode("-", $request->token_monitoring);
            if(count($explode_token) === 3){
                $id_zonasi_satker=Hashids::decode($explode_token[0]);
                $id_satker=Hashids::decode($explode_token[1]);
                $jumlah_data=Hashids::decode($explode_token[2]);
                $id_satker_user=$request->user()->IdSatker;
                if(empty($id_zonasi_satker) || empty($id_satker) || empty($jumlah_data) || $id_satker_user !== $id_satker[0]){
                    $msg="Pengiriman Penilaian tidak dapat dilakukan. :1";
                }else{
                    $send_to_badilum=$this->zonasiSatkerService->sendPenilaianToBadilum($id_zonasi_satker[0], $id_satker[0], $jumlah_data[0]);
                    $status=$send_to_badilum['status'];
                    $msg=$send_to_badilum['msg'];
                }
            }else{
                $msg="Pengiriman Penilaian tidak dapat dilakukan. :2";
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }
}
