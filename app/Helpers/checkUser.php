<?php
    use App\Models\Tref_users;
    use Carbon\Carbon;
    use App\Models\Satker;
    use App\Models\Tref_sys_config;
use App\Models\Tref_zonasi;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

    if(!function_exists('checkDataAdminSatker')){
        function checkDataAdminSatker($username){
            $checkUser=Tref_users::where('uname', $username)
                        ->whereRaw('IdRole >= 3')
                        ->whereRaw('IdRole <= 4')
                        ->first();
            if(!is_null($checkUser)){
                $id_pegawai=(int)$checkUser['IdPegawai'];
                if($id_pegawai > 0){
                    return true;
                }
            }
            return false;
        }
    }

    if(!function_exists('sendWa')){
        function sendWa($msg_wa, $nip, $nama, $reciver){
            $get_env=Tref_sys_config::where("config_name", "environment")->first();
            $env=strip_tags($get_env['config_value_str']);
            if($env === "production"){
                $data=[
                    'token'=>config('services.WA_MA.token'),
                    'nip'=>$nip,
                    'message'=>$msg_wa,
                    'phoneNumber'=>$reciver,
                    'name'=>$nama
                ];
            }else if($env === "testing" || $env === "development"){
                $data=[
                    'token'=>config('services.WA_MA.token'),
                    'nip'=>"199306242019031004",
                    'message'=>$msg_wa,
                    'phoneNumber'=>"081361355590",
                    'name'=>"Edi Daulatta Sembiring"
                ];
            }

            // $data=[
            //     'token'=>config('services.WA_MA.token'),
            //     'nip'=>$nip,
            //     'message'=>$msg_wa,
            //     'phoneNumber'=>$reciver,
            //     'name'=>$nama
            // ];

            $data_post=json_encode($data);
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://bisdev.mahkamahagung.go.id:8081/api/v2/wa-notification',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data_post,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer pBSpYVQFb3znpLfdaBdtkkJk-oPdObpt-RvGBNiF',
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $response_dec=json_decode($response);
            return [
                'status'=>$response_dec->status,
                'msg'=>$response_dec->message
            ];
        }


        //     //wa ma
        //     $msg="";
        //     $waUrl = 'https://webservice.mahkamahagung.go.id/';
        //     // $this->token = 'c97f462b-b1aa-4417-b0a0-ab146c8c954e';
        //     $token = 'e25ca442-c4dd-4e7b-bdbb-ccd95c90f7d7';
            
        //    $body = $msg_wa;
        //     // $body .= PHP_EOL . PHP_EOL . "Silahkan login ke " . $this->baseUrl . " untuk info lebih lanjut." . PHP_EOL . "Terima Kasih.";

        //     $headers    = array(
        //         'User-Agent: SIGANIS Badilum',
        //         // 'token: d4b32588-ec99-4262-b6f9-4888bd13b628',
        //         'token: ' . $token,
        //         'names: siganis',
        //         'Content-Type: application/json'
        //     );
        //     $get_config=Tref_sys_config::where('config_name', 'environment')->first();
        //     if(!is_null($get_config)){
        //         $env=strip_tags($get_config['config_value_str']);
        //         if($env === "production"){
        //             $telepon=$reciver;
        //         }else if($env === "testing" || $env === "development"){
        //             $telepon_arr=["081273861528", "0895397184103", "082144819197"];
        //             $rand=rand(0,2);
        //             $telepon=$telepon_arr[$rand];
        //         }
        //     }else{
        //         $telepon="081273861528";
        //     }
        //     $postfield  = json_encode(array(
        //         "variable"  => "_Ini adalah pesan otomatis Aplikasi Sistem Pembinaan Tenaga Teknis (SIGANIS)_",
        //         "variable2" =>  preg_replace("/\n/m", '\n', "$body"),
        //         "phone"     =>  "$telepon",
        //     ));

        //     $curl = curl_init();

        //     curl_setopt_array($curl, array(
        //         // CURLOPT_URL                 => 'https://webservice.mahkamahagung.go.id/wa_gateway/send_wa',
        //         CURLOPT_URL                 => $waUrl . 'wa_gateway/send_wa',
        //         CURLOPT_RETURNTRANSFER      => true,
        //         CURLOPT_ENCODING            => '',
        //         CURLOPT_MAXREDIRS           => 10,
        //         CURLOPT_TIMEOUT             => 0,
        //         CURLOPT_FOLLOWLOCATION      => true,
        //         CURLOPT_HTTP_VERSION        => CURL_HTTP_VERSION_1_1,
        //         CURLOPT_SSL_VERIFYPEER      => false,
        //         CURLOPT_SSL_VERIFYHOST      => false,
        //         CURLOPT_CUSTOMREQUEST       => 'POST',
        //         CURLOPT_POSTFIELDS          => $postfield,
        //         CURLOPT_HTTPHEADER          => $headers,
        //     ));

        //     $response = curl_exec($curl);

        //     $info = curl_getinfo($curl);
        //     curl_close($curl);
        //     $response = json_decode($response);
        //     $status=$response->status;
        //     if ($info['http_code'] != 200) {
        //         $msg = "HTTP Error API WA. (HTTP " . $info['http_code'] . ")";
        //         $msg .= "\nPostdata:\n" . $postfield;
        //         $msg .= "\nResponse:\n" . $response;
        //         $status=false;
        //         // return false;
        //     }
            
        //     if (json_last_error() !== JSON_ERROR_NONE) {
        //         $msg = "Komunikasi Bermasalah dengan Server API WA";
        //         // return false;
        //     }
        //     if ($response->status != 'ok') {
        //         $msg = $response->response;
        //         // return false;
        //     }
            
        //     return [
        //         'status' => $response->status,
        //         'msg' => $msg
        //     ];
    }

    if(!function_exists('getZonaWaktuSatker')){
        function getZonaWaktuSatker($id_satker){
            $get_data=Satker::where('IdSatker', $id_satker)->first();
            return $get_data['TimeZone'];
        }
    }

    if(!function_exists('convertTimeZone')){
        function convertTimeZone($time_zone){
            $jakarta_time=Carbon::parse(date('Y-m-d H:i:s'), 'Asia/Jakarta');
            if((int)$time_zone === 1){
                $zone_time=$jakarta_time->setTimezone('Asia/Makassar');
            }else if((int)$time_zone === 2){
                $zone_time=$jakarta_time->setTimezone('Asia/Jayapura');
            }else if((int)$time_zone === 0){
                $zone_time=$jakarta_time->setTimezone('Asia/Jakarta');
            }

            return $zone_time->format('Y-m-d H:i:s');
        }
    }

    if(!function_exists('getMsg')){
        function getWAMsg($category, $kode, $additional_data=null){
            if($category === "new_admin"){
                $msg="Mata360 - Pemberitahuan !!!\nMasukan angka ini:\n\n".$kode."\n\nuntuk melakukan Verifikasi data.\nTerimakasih.\n\nSilahkan menjawab Pesan ini dengan Ya untuk verifikasi anda menerimanya.";
                $msg.="\n\nCatatan: Token akan expired dalam 5 menit";
            }else if($category === "jabatan_kosong"){
                $get_config=Tref_sys_config::where('config_name', 'web_url')->first();
                $url=$get_config['config_value_str'];
                $msg="Mata360 - Pemberitahuan !!!\nKepada Yth. Admin Satuan Kerja Pengadilan.\n\nDalam rangka akan dilaksanakannya Penilaian Mata 360, diharapkan Bapak / Ibu Admin Satuan Kerja untuk melengkapi Jabatan Kosong yang ada di satuan kerja anda.\n\nSilahkan akses halaman Mata360 melalui link dibawah ini:\n\n\n".$url."\n\n\nTerimakasih.";
            }else if($category === "notif_penilaian"){
                $get_config=Tref_sys_config::where('config_name', 'web_url')->first();
                $url=$get_config['config_value_str'];
                $msg="Mata360 - Pemberitahuan !!!\nKepada Yth. Bapak / Ibu ".$kode."\nAnda merupakan salah satu Peserta Penilaian Mata - 360.\n\nPenilaian 360 dapat diakses melalui alamat:\n\n".$url."/penilaian/".$additional_data."\n\nSilahan melakukan penilaian melalui Link tersebut. Terimakasih.\n\nDirektorat Jenderal Badan Peradilan Umum - Pembinaan Tenaga Teknis\n\nSilahkan menjawab pesan ini denga YA, untuk konfirmasi peserta";
            }else if($category === "notif_send_penilaian_badilum"){
                $msg="Mata360 - Pemberitahuan !!!\nKepada Yth. Bapak / Ibu Admin Badilum.\n\nSeluruh Data Persiapan untuk memulai Penilaian sudah lengkap. Silahkan  mengirimkan notifikasi pesan Whatsapp ke peserta untuk tahapan selanjutnya\n\nTerimakasih";
            }

            
            return $msg;
        }

        if(!function_exists('sendWaObserveeHelper')){
            function sendWaObserveeHelpers(){
                $status_log="error";
                try{
                    DB::beginTransaction();
                        $get_zonasi=Tref_zonasi::where('proses_id', 5)
                                ->where('sent_notif_peserta', false)
                                ->get();
                        foreach($get_zonasi as $list_zonasi){
                            $zonasiSatkerService=resolve(\App\Services\zonasiSatkerService::class);
                            $create_job=$zonasiSatkerService->createJobSendWA($list_zonasi['IdZona']);
                            
                            $status_job=$create_job['status'];
                            $msg_job=$create_job['msg'];
                            if($status_job === "ok"){
                                $status_log="finished";
                                $msg_job="Berhasil mengirimkan Pesan Whatsapp ke Admin Badilum untuk mengirimkan Notifikasi";
                            }
                            $zonasiService=resolve(\App\Services\zonasiService::class);
                            $zonasiService->saveLog($list_zonasi['IdZona'], "send_notif", $msg_job, $status_log);
                        }
                    DB::commit();
                }catch(\Exception $e){
                    DB::rollBack();
                    $msg=$e->getMessage();
                    $zonasiService=resolve(\App\Services\zonasiService::class);
                    $zonasiService->saveLog($list_zonasi['IdZona'], "send_notif", $msg, $status_log);
                }
            }
        }

        if(!function_exists('generateSignature')){
            function generateSignature($hashed_payload){
                $payload=json_encode(['payload'=>$hashed_payload]);
                $secret=config('app.hmac_secret');
                $signature=hash_hmac('sha256', $payload, $secret);

                return $signature;
            }
        }

        if(!function_exists('generateNilaiPesertaZonasi')){
            function generateNilaiPesertaZonasi($id_peserta_zonasi, $id_zonasi_satker){
                
            }
        }

        if(!function_exists('enc')){
            function encodeInt($int){
                return Hashids::encode($int);
            }
        }

        if(!function_exists('dec')){
            function decodeInt($int){
                return Hashids::decode($int);
            }
        }

        if(!function_exists('encKeyReportIndividu')){
            function encKeyReportIndividu($id_observee, $id_zonasi_satker, $jlh_penilai){
                $str_key_open="sImA1010nG";
                $id_observee_enc=Crypt::encrypt($id_observee);
                $gabung=(int)$id_zonasi_satker+(int)$jlh_penilai;
                $gabung_enc=Hashids::encode($gabung);
                $id_zonasi_satker_encode=Hashids::encode($id_zonasi_satker);
                $panjang_=strlen($id_observee_enc);
                $half_panjang=ceil($panjang_/2);
                $split_str=str_split($id_observee_enc, $half_panjang);
                $string_tengah=$str_key_open."".$gabung_enc."IdZs4t".$id_zonasi_satker_encode."".$str_key_open;
                $key_report=$split_str[0]."".$string_tengah."".$split_str[1];
                return $key_report;
            }
        }

        if(!function_exists("decKeyReportIndividu")){
            function decKeyReportIndividu($key){
                $str_key_open="sImA1010nG";
                $explode=explode($str_key_open, $key);
                if(count($explode) === 3){
                    $key_1=$explode[0];
                    $key_2=$explode[1];
                    $key_3=$explode[2];
                    $explode_key2=explode("IdZs4t", $key_2);
                    if(count($explode_key2) === 2){
                        $gabungan=$explode_key2[0];
                        $id_zonasi_satker_encode=$explode_key2[1];
                        $id_zonasi_satker_enc=Hashids::decode($id_zonasi_satker_encode);
                        $gabungan_decode=Hashids::decode($gabungan);
                        if(!empty($id_zonasi_satker_enc) && !empty($gabungan_decode)){
                            $id_zonasi_satker=$id_zonasi_satker_enc[0];
                            $gabungan=$gabungan_decode[0];
                            $jlh_penilai=(int)$gabungan - $id_zonasi_satker;
                            $id_observee_gabung_enc=$key_1."".$key_3;
                            try{
                                $id_observee_dec=Crypt::decrypt($id_observee_gabung_enc);
                                return ['id_observee'=>$id_observee_dec, 'id_zonasi_satker'=>$id_zonasi_satker, 'jlh_penilai'=>$jlh_penilai];
                            }catch(DecryptException $e){
                                return 0;
                            }
                        }
                    }
                }
                return 0;
            }
        }
    }

?>