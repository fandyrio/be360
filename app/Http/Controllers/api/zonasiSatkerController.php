<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Jobs;
use App\Models\Log_msg;
use App\Models\Tref_sys_config;
use Illuminate\Http\Request;
use App\Services\zonasiSatkerService;
use DateTime;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
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

    /**
     * (Admin Satker) Detil Zonasi Satker
     *
     * Endpoint untuk mengambil data detil zonasi untuk admin satker.
     *
     *@group Zonasi
     *
     *
     *
     * @urlParam id_zonasi_satker_enc string required.
     * 
     * 
     * @response 200 {
     * 
     * }
     */
    
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

    /**
     * Jabatan Kosong(Admin Satker)
     *
     * Endpoint untuk mengambil data jabatan kosong dan majelis hakim.
     *
     *@group Zonasi
     *
     *
     *
     * @urlParam id_zonasi_satker_enc string required.
     * 
     * 
     * @response 200 {
     * 
     * }
     */
    public function getJabatanKosongSatker($id_zonasi_satker_enc, Request $request){
        $status=false;
        $data=[];
        $msg="";
        $id_satker=0;
        $send_confirm = false;
        $data_majelis = null;
        $jumlah_hakim = 0;
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
            $data_majelis = $get_data['data_majelis'];
            $jumlah_hakim = $get_data['jumlah_hakim'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'send_confirm'=>$send_confirm, 'data'=>$data, 'data_majelis'=>$data_majelis, 'jumlah_hakim'=>$jumlah_hakim]);
    }


    /**
     * Majelis Hakim(Admin Satker)
     *
     * Endpoint untuk mengambil data  majelis hakim.
     *
     *@group Zonasi
     *
     *
     *
     * @urlParam id_zonasi_satker_enc string required. Example: oRqKKENk
     * 
     * 
     * @response 200 {
     * 
     * }
     */
    public function getMajelisHakimSatker($id_zonasi_satker_enc, Request $request){
        $status=false;
        $data=[];
        $msg="";
        $id_satker=0;
        $send_confirm = false;
        $data_majelis = null;
        $jumlah_hakim = 0;
        if(isset($request->user()->IdSatker)){
            $id_satker=$request->user()->IdSatker;
        }
        try{
            $id_zonasi_satker=Hashids::decode($id_zonasi_satker_enc);
            if(empty($id_zonasi_satker)){
                throw new \Exception("Invalid token Zonasi Satker");
            }
            $get_data=$this->zonasiSatkerService->getMajelisHakim($id_zonasi_satker);
            $status=true;
            $msg=$get_data['status_kelengkapan'];
            $send_confirm=$get_data['send_confirm'];
            $data_majelis = $get_data['list_majelis'];
            $jumlah_hakim = $get_data['jumlah_hakim'];
        }catch(\Exception $e){
            $msg=$e->getMessage();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg, 'send_confirm'=>$send_confirm, 'data_majelis'=>$data_majelis, 'jumlah_hakim'=>$jumlah_hakim]);
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

    /**
     * Get Hakim By NIP(Admin Satker)
     *
     * Endpoint untuk mengambil data personal hakim.
     *@authenticated
     *@group Zonasi
     *
     *
     *
     * @bodyParam identity string required nama_or_nip. Example: Ratna
     * @bodyParam token_zonasi_satker required token_zonasi_satker example: 2892
     * 
     * 
     * @response 200 {
     *      "status": true,
     *      "msg": "Data Found",
     *      "data": [
     *           "nama": "Ratna Dewi",
     *           "nip": "1992010101010101010",
     *           "token_pegawai": "random_string"
     *       ]
     * }
     */
    public function getHakimByNameNip(Request $request){
        $status = false;
        $data = [];
        try{
            $request->validate([
                'identity'=>['required', 'string'],
                'token_zonasi_satker'=>['required', 'string']
            ]);
            $id_zonasi_satker = Hashids::decode($request->token_zonasi_satker);
            if(empty($id_zonasi_satker)){
                return response()->json(['status'=>false, 'msg'=>'Invalid data Hakim']);
            }

            $id_satker = 0;
            if(isset($request->user()->IdSatker)){
                $id_satker = $request->user()->IdSatker;
            }

            $get_hakim = $this->zonasiSatkerService->getHakimByNameNip($request->identity, $id_satker, $id_zonasi_satker[0]);
            $status = $get_hakim['status'];
            $msg = $get_hakim['msg'];
            $data = $get_hakim['data'];

        }catch(ValidationException $e){
            $msg = $e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg, 'data'=>$data]);
    }


    /**
     * Save Majelis(Admin Satker)
     *
     * Endpoint untuk menyimpan data majelis hakim.
     *@authenticated
     *@group Zonasi
     *
     *
     *
     * @bodyParam nama_majelis string required. Example: Majelis 1
     * @bodyParam token_hakim string[] required Array token hakim Example: ["abc123","def456","ghi789"]
     * @bodyParam token_zonasi_satker string required. Example: 9zn5pBNn
     * 
     * 
     * @response 200 {
     *      "status": true,
     *      "msg": "Berhasil disimpan",
     *      
     * }
     */
    public function saveMejelis(Request $request){
        $status = false;
        $validator = Validator::make($request->all(), [
            'token_hakim'=>['required', 'array', 'size:3'],
            'token_hakim.*'=>['string'],
            'nama_majelis'=>['required', 'string'],
            'token_zonasi_satker'=>['required', 'string']
        ]);

        if($validator->fails()){
            return response()->json([
                'status'=>false,
                'msg'=>$validator->errors()->first()
            ], 422);
        }

        try{
            $jlh_hakim = count($request->token_hakim);
            if($jlh_hakim < 3 || $jlh_hakim > 3){
                throw new \Exception('Jumlah Hakim harus 3 orang');
            }

            $jlh_hakim_tidak_valid = 0;
            $data_hakim = [];
            for($x=0;$x<$jlh_hakim;$x++){
                $decode_hakim = Hashids::decode($request->token_hakim[$x]);
                if(empty($decode_hakim)){
                    $jlh_hakim_tidak_valid+=1;
                }else{
                    $data_hakim[] = $decode_hakim[0];
                }
            }
            if($jlh_hakim_tidak_valid > 0){
                throw new \Exception('Data hakim tidak valid. Pastikan input data hakim melalui form yang tepat');
            }

            $id_zonasi_satker = Hashids::decode($request->token_zonasi_satker);
            if(empty($id_zonasi_satker)){
                throw new \Exception("Data Anda tidak valid");
            }

            $data_hakim_sama = 0;
            $jlh_pimpinan_adhoc = 0;
            for($x=0;$x<count($data_hakim);$x++){
                if($data_hakim[$x] === 1 || $data_hakim[$x] === 0){
                    //berarti ada pimpinan atau adhoc
                    $jlh_pimpinan_adhoc+=1;
                }
                for($y=0;$y<count($data_hakim);$y++){
                    if($data_hakim[$y] === 1 || $data_hakim[$y] === 0){
                        break;
                    }
                    if((int)$data_hakim[$x] === (int)$data_hakim[$y] && $x !== $y){
                        $data_hakim_sama+=1;
                    }
                }
            }

            if($jlh_pimpinan_adhoc === count($data_hakim)){
                throw new \Exception("Tidak perlu diinput");
            }

            if((int)$data_hakim_sama > 0){
                throw new \Exception("Ada hakim yang sama. Silahkan dilakukan pengecekan ulang");
            }

            $save_majelis = $this->zonasiSatkerService->saveMejelisHakim(clean($request->nama_majelis), $data_hakim, $id_zonasi_satker[0]);
            $status = $save_majelis['status'];
            $msg = $save_majelis['msg'];

        }catch(\Exception $e){
            $msg = $e->getMessage()." ".$e->getLine();
        }

        return response()->json(['status'=>$status, 'msg'=>$msg]);
    }

    /**
     * Admin Satker - Delete Majelis
     *@header X-Signature signature dari dari detil zonasi satker.Example: 3549483789c6ea4914fb842295a0ebead654fc3fa74196e5afd882e5bef27384
     * Endpoint untuk menghapus data majelis hakim.
     *@authenticated
     *@group Zonasi
     *
     *
     *
     * @bodyParam token_majelis string required. Example: 
     * @bodyParam token_zonasi_satker string required. Ambil dari detil zonasi Example: Lz0lb6zD
     * @bodyParam payload string required. Example: trans_zonasi_satker
     * 
     * 
     * @response 200 {
     *      "status": true,
     *      "msg": "Berhasil disimpan",
     *      
     * }
     */
    public function deleteMajelisHakim(Request $request){
        $status = false;
        try{
            $request->validate([
                'token_majelis'=>['required', 'string'],
                'token_zonasi_satker'=>['required', 'string'],
                'payload'=>['required', 'string']

            ]);
            $token_majelis = explode("-", $request->token_majelis);
            $jumlah = count($token_majelis);
            if($jumlah === 3){
                $id_periode_enc = $token_majelis[0];
                $id_zonasi_satker_enc = $token_majelis[1];
                $nama_majelis_enc = $token_majelis[2];

                $id_periode = Hashids::decode($id_periode_enc);
                $id_zonasi_satker = Hashids::decode($id_zonasi_satker_enc);
                
                try{
                    $nama_majelis = Crypt::decrypt($nama_majelis_enc);
                    if(empty($id_periode) || empty($id_zonasi_satker)){
                        $msg = "Invalid token zonasi anda";
                    }else{
                        $delete = $this->zonasiSatkerService->deleteMajelisHakim($id_periode, $id_zonasi_satker, $nama_majelis);
                        $status = $delete['status'];
                        $msg = $delete['msg'];
                    }
                }catch(DecryptException $e){
                    $msg = "Invalid token majelis hakim";
                }
                

            }else{
                $msg = "Invalid token majelis";
            }
        }catch(ValidationException $e){
            $msg = $e->validator->errors()->first();
        }
        return response()->json(['status'=>$status, 'msg'=>$msg]);
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

    /**
     * Confirm Jabatan Kosong(Admin Satker)
     *
     * Endpoint konfirmasi jabatan kosong dan majelis hakim.
     *@authenticated
     *@group Zonasi
     *
     *@header X-Signature signature dari dari detil zonasi satker.Example: 3549483789c6ea4914fb842295a0ebead654fc3fa74196e5afd882e5bef27384
     *
     * @bodyParam token_zonasi_satker string required. Example: Lz0lb6zD
     * @bodyParam payload string required. Example: test
     * 
     * 
     * @response 200 {
     *      "status": true,
     *      "msg": "Berhasil disimpan",
     *      
     * }
     */
    public function sendConfirmJabatanKosong(Request $request){
        $status_majelis=false;
        $msg = "";
        try{
            $request->validate([
                'token_zonasi_satker'=> ['required', 'string'],
                'payload'=>['required']
            ]);
            $id_zonasi_satker=Hashids::decode($request->token_zonasi_satker);
            if(empty($id_zonasi_satker)){
                return response()->json(['status'=>false, 'msg'=>"Invalid token Zonasi Satker"]);
            }
            $uname = $request->user()->uname;

            $send=$this->zonasiSatkerService->sendConfirmJabatanKosong($id_zonasi_satker[0]);
            $status_jabatan_kosong=$send['status'];
            $msg.=$send['msg']."\n";
            $id_zonasi = $send['id_zonasi'];
            if($status_jabatan_kosong ===  true){
                $send_majelis = $this->zonasiSatkerService->sendConfirmMajelisHakim($id_zonasi_satker[0]);
                $msg.= $send_majelis['msg']."\n";
                $status_majelis=$send_majelis['status'];
                if($status_majelis === true){
                    $id_periode = $send_majelis['id_periode'];
                    $this->zonasiSatkerService->generateJobSendWA($id_zonasi, $id_periode, $uname);

                }
            }
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status_majelis, 'msg'=>$msg]);
    }

     /**
     * Confirm Majelis Hakim(Admin Satker)
     *
     * Endpoint konfirmasi data majelis hakim.
     *@authenticated
     *@group Zonasi
     *
     *@header X-Signature signature dari dari detil zonasi satker.Example: 3549483789c6ea4914fb842295a0ebead654fc3fa74196e5afd882e5bef27384
     *
     * @bodyParam token_zonasi_satker string required. Example: Lz0lb6zD
     * @bodyParam payload string required. Example: test
     * 
     * 
     * @response 200 {
     *      "status": true,
     *      "msg": "Berhasil disimpan",
     *      
     * }
     */
    public function sendConfirmMajelisHakim(Request $request){
        $status_majelis=false;
        $msg = "";
        try{
            $request->validate([
                'token_zonasi_satker'=> ['required', 'string'],
                'payload'=>['required']
            ]);
            $id_zonasi_satker=Hashids::decode($request->token_zonasi_satker);
            if(empty($id_zonasi_satker)){
                return response()->json(['status'=>false, 'msg'=>"Invalid token Zonasi Satker"]);
            }
            // $uname = $request->user()->uname;

            $send_majelis = $this->zonasiSatkerService->sendConfirmMajelisHakim($id_zonasi_satker[0]);
            $msg= $send_majelis['msg']."\n";
            $status_majelis=$send_majelis['status'];
        }catch(ValidationException $e){
            $msg=$e->validator->errors()->first();
        }

        return response()->json(['status'=>$status_majelis, 'msg'=>$msg]);
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
                    $jlh_menit=ceil($jumlah_jobs / $msg_per_minutes);
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

                    exec("php ".base_path('artisan'). " queue:work --queue=send_wa_peserta_".$id_zonasi[0]." --sleep=10 --tries=3 --timeout=120 --max-time=".$max_time." > /dev/null 2>&1 &");
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
            if($page <= 0){
                $page = 1;
            }
            $limit=50;
            if(is_null($refresh) || $refresh === "null"){
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
        return response()->json(['status'=>$status, 'msg'=>$msg, 'send_to_badilum'=>$send_to_badilum, 'jumlah_halaman'=>$jumlah_halaman, 'page'=>$page, 'percentage'=>$percentage, 'sudah_menilai'=>$sudah_menilai, 'total_penilaian'=>$total_penilaian, 'token_monitoring'=>$token_monitoring, 'signature'=>$signature, 'data'=>$data, 'refresh'=>$refresh]);
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
