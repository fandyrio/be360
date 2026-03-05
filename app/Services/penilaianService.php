<?php
    namespace App\Services;

use App\Models\Satker;
use App\Models\Trans_bobot_penilaian_periode;
use App\Models\Trans_nilai_peserta_zonasi;
use App\Models\Trans_observee;
use App\Models\Trans_pertanyaan_periode;
use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_jabatan_peserta;
use App\Models\Tref_jawaban_bundle;
use App\Models\Tref_zonasi;
use App\Models\Zonasi_satker;
use DateTime;
use Illuminate\Support\Facades\Crypt;
    use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids; 

    class penilaianService{
        public function validateParamsPenilaian($id_pegawai, $id_nama_jabatan, $id_zonasi_satker){
            $endpoint="";
            $signature="";
            $status=false;
            $msg="";
            $token_penilaian="";
            $get_observee=Trans_observee::where('IdPegawai', $id_pegawai)
                                        ->where('IdNamaJabatan', $id_nama_jabatan)
                                        ->where('IdZonaSatker', $id_zonasi_satker)
                                        ->first();
            if(!is_null($get_observee)){
                $peserta_dinilai=Trans_peserta_zonasi::where('id_pegawai_penilai', $get_observee['IdObservee'])->exists();
                if($peserta_dinilai){
                    $data_penilain=$this->generateDataPenilaianPhaseOne($get_observee['IdObservee'], $get_observee['NIPBaru'], $id_zonasi_satker);
                    $signature=$data_penilain['signature'];
                    $endpoint=$data_penilain['endpoint'];
                    $token_penilaian=$data_penilain['payload'];
                    $status=true;
                }else{
                    $msg="Tidak ada peserta yang dinilai";
                }
            }else{
                $msg="Data tidak ditemukan. Kesalahan ini telah direkam. Silahkan hubungi Administrator";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'endpoint'=>$endpoint,
                'token_penilaian'=>$token_penilaian
            ];
        }

        public function generateDataPenilaianPhaseOne($id_observee, $nip_penilai, $id_zonasi_satker){
            $payload_data=Hashids::encode($nip_penilai)."atAMObE".Hashids::encode($id_zonasi_satker);
            $signature=generateSignature($payload_data);
            $endpoint=Crypt::encrypt(Hashids::encode($id_observee));
            
            return [
                'signature'=>$signature,
                'endpoint'=>$endpoint,
                'payload'=>$payload_data
            ];
        }

        public function getDataPenilaian($id_observee, $nip_penilai, $id_zonasi_satker){
            $status=false;
            $total=0;
            $selesai=0;
            $data=[];
            $get_observee=Trans_observee::where('IdObservee', $id_observee)
                                    ->where('NIPBaru', $nip_penilai)
                                    ->where('IdZonaSatker', $id_zonasi_satker)
                                    ->first();
            $data['penilai']=null;
            $data['peserta']=null;
            if(!is_null($get_observee)){
                $get_peserta=Trans_peserta_zonasi::join('trans_observee as to', 'to.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_peserta')
                                            ->join('tref_pegawai as tp1', 'tp1.id_pegawai', '=', 'to.IdPegawai')
                                            ->join('trans_observee as to2', 'to2.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_penilai')
                                            ->join('tref_pegawai as tp2', 'tp2.id_pegawai', '=', 'to2.IdPegawai')
                                            ->select('trans_peserta_zonasi.*', 
                                                'to.IdObservee as id_observee_peserta', 
                                                'to.IdPegawai as id_pegawai_peserta', 
                                                'to.NIPBaru as nip_peserta', 
                                                'to.NamaJabatan as jabatan_peserta', 
                                                'to.IdZonaSatker as id_zonasi_satker_peserta', 
                                                'to.total_nilai as nilai_peserta',
                                                'tp1.nama_pegawai as nama_peserta', 
                                                'tp1.foto_pegawai as foto_peserta', 
                                                'to2.IdObservee as id_observee_penilai', 
                                                'to2.NIPBaru as nip_penilai', 
                                                'to2.NamaJabatan as jabatan_penilai', 
                                                'to2.IdPegawai as id_pegawai_penilai',
                                                'tp2.nama_pegawai as nama_penilai', 
                                                'tp2.foto_pegawai as foto_penilai',
                                                'trans_peserta_zonasi.id as id_peserta_zonasi')
                                            ->where("id_pegawai_penilai", $id_observee)
                                            ->where('to2.NIPBaru', $nip_penilai)
                                            ->where("to2.IdZonaSatker", $id_zonasi_satker)
                                            ->get();
                $jumlah_peserta=$get_peserta->count();
                if($jumlah_peserta > 0){
                    $status=true;
                    $x=0;
                    $msg="Data Found";
                    $params_before=null;
                    foreach($get_peserta as $list_peserta){
                        if($x === 0){
                            $data['penilai']['nama']=$list_peserta['nama_penilai'];
                            $data['penilai']['nip_penilai']=$list_peserta['nip_penilai'];
                            $data['penilai']['foto_penilai']=$list_peserta['foto_penilai'];

                        }
                        $params=$list_peserta['id_observee_peserta']."paramsdata".$list_peserta['id_observee_penilai']."paramsdata".$list_peserta['id_zona_satker'];
                        if(is_null($params_before) || $params_before !== $params){
                            $data['peserta'][$x]['nama']=$list_peserta['nama_peserta'];
                            $data['peserta'][$x]['foto_peserta']=$list_peserta['foto_peserta'];
                            $data['peserta'][$x]['nip_peserta']=$list_peserta['nip_peserta'];
                            $data['peserta'][$x]['nilai']=$list_peserta['nilai_peserta'];
                            $data['peserta'][$x]['status_nilai']=(float)$list_peserta['nilai_peserta'] > 0 ? "finished" : "notyet";
                            $data['peserta'][$x]['foto_peserta']=$list_peserta['foto_peserta'];
                            $data['peserta'][$x]['jabatan']=$list_peserta['jabatan_peserta'];
                            $data['peserta'][$x]['params']=Crypt::encrypt($params);
                            if((float)$list_peserta['nilai_peserta'] > 0){
                                $selesai+=1;
                            }
                            $x++;
                        }
                        $params_before=$params;
                    }
                    $total=$x;
                }else{
                    $msg="Data Peserta tidak ditemukan";
                }
            }else{
                $msg="Data Penilai tidak valid";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'total'=>$total,
                'selesai'=>$selesai,
                'data'=>$data
            ];
        }

        public function getJawabanTextByBundlePoint($bundle, $point){
            $get_data=Cache::store('redis')->remember('bundle_jawaban_active', 3600*24*2, function (){
                $row = Tref_jawaban_bundle::where('active', true)->get();
                $data_jawaban=[];
                foreach($row as $list_bundle_jawaban){
                    $data_jawaban[$list_bundle_jawaban['bundle_code']]["point_{$list_bundle_jawaban['point_jawaban']}"]=$list_bundle_jawaban['jawaban_text'];
                }
                return $data_jawaban;
            });

            return $get_data[$bundle]["point_{$point}"];
            
        }

        public function getPertanyaanPeriode($id_periode, $params = null){
            // $get_pertanyaan_periode=Cache::remember("pertanyaan_oer_periode_{$id_periode}", "3600", function () use($id_periode){
            //         return Trans_pertanyaan_periode::where('id_periode', $id_periode)->get(); 
            //     });
            $get_pertanyaan_periode_static=Cache::store('redis')->remember("ref_pertanyaan_periode_{$id_periode}", 3600*24*365, function () use($id_periode){
                        return Trans_pertanyaan_periode::join("variable_pertanyaan as vp", 'vp.id', '=', 'trans_pertanyaan_periode.id_variable')
                                                ->select('trans_pertanyaan_periode.pertanyaan', 
                                                'trans_pertanyaan_periode.bundle_code_jawaban', 
                                                'trans_pertanyaan_periode.bobot', 
                                                'trans_pertanyaan_periode.id as id_pertanyaan_periode',
                                                'trans_pertanyaan_periode.id_pertanyaan as id_ref_pertanyaan',
                                                'vp.variable', 
                                                'vp.kriteria', 'vp.id as id_variable')
                                                ->where('id_periode', $id_periode)
                                                ->orderBy('trans_pertanyaan_periode.id', 'asc')
                                                ->get();
                    });
            $x=0;
            foreach($get_pertanyaan_periode_static as $list_pertanyaan_periode){
                $id_pertanyaan_periode[$x]=$list_pertanyaan_periode['id_pertanyaan_periode'];
                $data["pertanyaan_{$list_pertanyaan_periode['id_pertanyaan_periode']}"]=$list_pertanyaan_periode['bundle_code_jawaban'];
                $x++;
            }

            if(!is_null($params) && $params === "getAll"){
                return $get_pertanyaan_periode_static;
            }else if(!is_null($params) && $params !== "getAll"){
                return $data["pertanyaan_{$params}"];
            }
            return $id_pertanyaan_periode;
        }

        public function getZonasi($id_zonasi_satker){
            $zonasi_satker=Cache::store('redis')->remember("zonasi_satker_{$id_zonasi_satker}", 3600*24*5, function () use($id_zonasi_satker) {
                return Zonasi_satker::join('tref_zonasi as tz', 'tz.IdZona', '=', 'trans_zonasi_satker.IdZona')
                                ->join('tref_tahun_penilaian as ttp', 'ttp.IdTahunPenilaian', '=', 'tz.IdTahunPenilaian')
                                ->select('ttp.IdTahunPenilaian as id_periode', 'tz.start_date', 'tz.end_date', 'trans_zonasi_satker.IdSatker', 'tz.proses_id')
                                ->where('trans_zonasi_satker.IdZonaSatker', $id_zonasi_satker)->first();
            });
            return $zonasi_satker;
        }

        public function getSatkerTimeZone($id_satker){
            $get_all_satker=Cache::store('redis')->remember('satker_badilum', "86400", function () {
                return Satker::select('IdSatker', 'TimeZone')->get();
            });
            $x=0;
            foreach($get_all_satker as $list_satker){
                $list_satker[$list_satker['IdSatker']]=$list_satker['TimeZone'];
            }

            return $list_satker[$id_satker];
        }

        public function validateZonasi($id_satker, $tgl_mulai, $tgl_selesai, $proses_id_zonasi){
            $get_timezone=$this->getSatkerTimeZone($id_satker);
            $date_now_time_zone=convertTimeZone($get_timezone);
            $format_date= date('Y-m-d', strtotime($date_now_time_zone));
            $new_date_timezone=new DateTime($format_date);
            $new_tgl_mulai=new DateTime($tgl_mulai);
            $new_tgl_selesai=new DateTime($tgl_selesai);
            if($new_date_timezone >= $new_tgl_mulai && $new_date_timezone <= $new_tgl_selesai && (int)$proses_id_zonasi === 5){
                return true;
            }
            return false;
        }

        public function getJabatanPesertaPenilai($id_observee_penilai, $id_observee_peserta){
            $status=false;
            $id_observee=[$id_observee_penilai, $id_observee_peserta];
            $get_observee=Trans_observee::whereIn('IdObservee', $id_observee)->get()->keyBy('IdObservee');
            $jumlah=$get_observee->count();
            if($jumlah === 2){
                $penilai=$get_observee[$id_observee_penilai];
                $peserta=$get_observee[$id_observee_peserta];
                $id_jabatan_penilai=$penilai->id_kelompok_jabatan;
                $id_jabatan_peserta=$peserta->id_kelompok_jabatan;
                if(((int)$id_jabatan_penilai === 15 || (int)$id_jabatan_penilai === 27) && (int)$id_jabatan_peserta === 15){
                    $status=true;
                }
            }

            return $status;
        }


        public function getPertanyaanPenilaian($id_observee_penilai, $id_observee_peserta, $nip_penilai, $id_zonasi_satker, $pemisahString){
            $can_edit=Hashids::encode(1);
            $status=false;
            $ada_plt=false;
            $keterangan="";
            $peserta=[];
            $nama_peserta=null;
            $nip_peserta=null;
            $jabatan_peserta=null;
            $msg="";
            $id_nilai_peserta_str=null;
            $hashed_id_periode=null;
            $token_penilaian_periode=null;
            $signature_periode=null;
            $data_pertanyaan_statis=[];

            //buat validasi untuk bisa isi nilai atau tidak
            
            $id_zonasi_satker_hashed=Hashids::encode($id_zonasi_satker);
            //1.Ambil data zonasi satker
            $ttl="3600";
            $zonasi_satker=$this->getZonasi($id_zonasi_satker);
            if(!is_null($zonasi_satker)){
                $id_periode=$zonasi_satker['id_periode'];
                $id_peserta_zonasi=[];
                $id_reference=[];
                $id_pertanyaan_periode=[];

                $id_satker=$zonasi_satker['IdSatker'];
                $tgl_mulai_zonasi=$zonasi_satker['start_date'];
                $tgl_selesai_zonasi=$zonasi_satker['end_date'];
                $proses_id_zonasi=$zonasi_satker['proses_id'];
                if(!$this->validateZonasi($id_satker, $tgl_mulai_zonasi, $tgl_selesai_zonasi, $proses_id_zonasi)){
                   $can_edit=Hashids::encode(0);
                }
                


                //2. Ambil Data Peserta
                $get_peserta_zonasi=Trans_peserta_zonasi::join('trans_observee as to', 'to.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_penilai')
                                                ->join('trans_observee as to2', 'to2.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_peserta')
                                                ->join('tref_pegawai as tp', 'tp.id_pegawai', '=', 'to2.IdPegawai')
                                                ->leftJoin('tref_jabatan_peserta as tjp', 'tjp.id', '=', 'trans_peserta_zonasi.id_jabatan_plt')
                                                ->where('trans_peserta_zonasi.id_pegawai_peserta', $id_observee_peserta)
                                                ->where('trans_peserta_zonasi.id_pegawai_penilai', $id_observee_penilai)
                                                ->where('id_zona_satker', $id_zonasi_satker)
                                                ->where('to.NIPBaru', $nip_penilai)
                                                ->select('trans_peserta_zonasi.*', 'tjp.jabatan', 'tp.nama_pegawai', 'tp.nip', 'to2.NamaJabatan', 'tp.foto_pegawai')
                                                ->orderBy('trans_peserta_zonasi.id_jabatan_plt', 'asc')
                                                ->get();

                $jumlah_peserta=$get_peserta_zonasi->count();
                if($jumlah_peserta > 0){
                    $status=true;
                    $x=0;
                    foreach($get_peserta_zonasi as $list_peserta){
                        $id_peserta_zonasi[$x]=$list_peserta['id'];
                        $id_reference[$x]=is_null($list_peserta['id_jabatan_plt']) ? null : $id_peserta_zonasi[0];
                        $nama_peserta[$x]=$list_peserta['nama_pegawai'];
                        $nip_peserta[$x]=$list_peserta['nip'];
                        $foto_pegawai[$x]=$list_peserta['foto_pegawai'];
                        $jabatan_peserta[$x]=$list_peserta['NamaJabatan'];
                        $locked=$list_peserta['status'] === 0 ? true : false;
                        $keterangan.=is_null($list_peserta['jabatan']) ? "" : "Juga menilai sebagai: ".$list_peserta['jabatan']."\n";
                        $x++;
                    }
                    $peserta['nama']=$nama_peserta[0];
                    $peserta['nip']=$nip_peserta[0];
                    $peserta['jabatan']=$jabatan_peserta[0];
                    $peserta['foto']=$foto_pegawai[0];

                    //3. Check Apakah sudah ada dalam table penilaian
                    $get_nilai_exists=Trans_nilai_peserta_zonasi::whereIn('id_peserta_zonasi', $id_peserta_zonasi)->exists();
                    if(!$get_nilai_exists){
                        $id_pertanyaan_periode=$this->getPertanyaanPeriode($id_periode);
                        $data_insert=[];
                        for($i_peserta=0;$i_peserta<$jumlah_peserta;$i_peserta++){
                            for($y=0;$y<count($id_pertanyaan_periode);$y++){
                                $data_insert[]=[
                                    'id_peserta_zonasi'=>$id_peserta_zonasi[$i_peserta],
                                    'id_pertanyaan'=>$id_pertanyaan_periode[$y],
                                    'id_reference'=>$id_reference[$i_peserta],
                                    'nilai'=>0,
                                    'locked'=>false
                                ];
                            }
                        }
                        DB::table('trans_nilai_peserta_zonasi')->insert($data_insert);
                    }

                     //5. Buat bundle jawaban untuk di cocokan ke pertanyaan
                    $get_bundle_jawaban=Cache::remember('jawaban_bundle', $ttl, function () {
                        return Tref_jawaban_bundle::where('active', true)
                                        ->orderBy('bundle_code', 'asc')
                                        ->orderBy('point_jawaban', 'desc')
                                        ->get(); 
                    });
                    $list_bundle_jawaban=[];
                    $bundle_code_before=null;
                    foreach($get_bundle_jawaban as $bundle_jawaban){
                        if($bundle_code_before !== $bundle_jawaban['bundle_code']){
                            $y=0;
                        }
                        $jawaban[$bundle_jawaban['bundle_code']][$y]['jawaban_text']=$bundle_jawaban['jawaban_text'];
                        
                        //generate code-jawaban
                        //id_peserta_zonasi-id_zonasi_satker-id_nilai-id_pertanyaan
                        $id_jawaban=$bundle_jawaban['id'];
                        $point_jawaban=$bundle_jawaban['point_jawaban'];
                        $code_jawaban_temp=Hashids::encode($id_jawaban)."-".Hashids::encode($point_jawaban);
                        //$token_pz_zs."".$pemisahString."".
                        //=========================================================================

                        $jawaban[$bundle_jawaban['bundle_code']][$y]['id_jawaban_text']=$code_jawaban_temp;
                        $y++;

                        $bundle_code_before=$bundle_jawaban['bundle_code'];
                    }

                    $get_pertanyaan_periode_static=$this->getPertanyaanPeriode($id_periode, "getAll");
                    $data_pertanyaan_statis=[];
                    $i_static=0;
                    $id_variable_before=null;
                    foreach($get_pertanyaan_periode_static as $list_static){
                        //generate signature
                            $hashed_pertanyaan_periode=Hashids::encode($list_static['id_pertanyaan_periode']);
                            $hashed_ref_pertanyaan=Hashids::encode($list_static['id_ref_pertanyaan']);
                            $pemisahString_1=$pemisahString[0];
                            $pemisahString_2=$pemisahString[1];
                            $pemisahString_3=$pemisahString[2];
                            $hashed_id_periode=Hashids::encode($id_periode);
                            $token_payload=$pemisahString[0].$hashed_pertanyaan_periode.$pemisahString[1].$hashed_ref_pertanyaan.$pemisahString[2].$hashed_id_periode."AtaM063".$pemisahString_1."AtaM063".$pemisahString_2."AtaM063".$pemisahString_3."3d1t4Bl3".$can_edit."pzh45H3d".$id_zonasi_satker_hashed;
                            $signature=generateSignature($token_payload);
                            

                        if($id_variable_before !== (int)$list_static['id_variable']){
                            if(!is_null($id_variable_before)){
                                $i_static++;
                            }
                            $data_pertanyaan_statis[$i_static]['variable']=$list_static['variable'];
                            $data_pertanyaan_statis[$i_static]['kriteria']=$list_static['kriteria'];
                            $data_pertanyaan_statis[$i_static]['daftar_pertanyaan']=[];
                            $i_pertanyaan=0;
                        }
                        ${"nilai_{$list_static['id_pertanyaan_periode']}"}[]=[
                            'text'=>null,
                            'id'=>null
                        ];
                        $data_pertanyaan_statis[$i_static]['daftar_pertanyaan'][] = [
                                'pertanyaan' => $list_static['pertanyaan'], 
                                'bobot' => $list_static['bobot'], 
                                'token_penilaian' => $token_payload,
                                // 'payload' => "data_variable_pertanyaan",
                                'signature' => $signature,
                                "nilai_{$list_static['id_pertanyaan_periode']}" => ${"nilai_{$list_static['id_pertanyaan_periode']}"},
                                "pilihan_jawaban" => $jawaban[$list_static['bundle_code_jawaban']]
                        ];
                        $id_variable_before=(int)$list_static['id_variable'];
                    }
                    $jumlah_variable=count($data_pertanyaan_statis);

                    //4. Ambil data penilaian dan pertanyaan
                    $get_existed_nilai=Trans_nilai_peserta_zonasi::select(
                                                    "trans_nilai_peserta_zonasi.id as id_nilai_pertanyaan", 
                                                    'trans_nilai_peserta_zonasi.nilai',  
                                                    "trans_nilai_peserta_zonasi.id_peserta_zonasi",
                                                    "trans_nilai_peserta_zonasi.nilai",
                                                    "trans_nilai_peserta_zonasi.id_pertanyaan",
                                                    "trans_nilai_peserta_zonasi.locked") 
                                                    ->whereIn('id_peserta_zonasi', $id_peserta_zonasi)
                                                    ->orderBy('trans_nilai_peserta_zonasi.id_peserta_zonasi', 'asc')
                                                    ->get();

                    if($get_existed_nilai->count() > 0){
                        $jlh_peserta_zonasi=count($id_peserta_zonasi);
                        $id_pz_hashed="";
                        for($i_hashed=0;$i_hashed<$jlh_peserta_zonasi;$i_hashed++){
                            $id_pz_hashed.=Hashids::encode($id_peserta_zonasi[$i_hashed]);
                            if($i_hashed < $jlh_peserta_zonasi-1){
                                $id_pz_hashed.="5pr4t3Pzh45H3d";
                            }
                        }

                        $id_peserta_zonasi_before=null;
                        $id_variable_before=null;
                        $a=0;$b=0;
                        $edit_nilai=[];
                        foreach($get_existed_nilai as $list_pertanyaan){
                            //untuk set 1 orang saja
                            if(is_null($id_peserta_zonasi_before) || (int)$list_pertanyaan['id_peserta_zonasi'] === (int)$id_peserta_zonasi_before){
                                if((int)$list_pertanyaan['locked'] === 1){
                                    $edit_nilai[$a]=0;
                                }
                                $jawaban_bundle_code=$this->getPertanyaanPeriode($id_periode, $list_pertanyaan['id_pertanyaan']);
                                $nilai=$list_pertanyaan['nilai'] === 0 ? "Belum dinilai" : $this->getJawabanTextByBundlePoint($jawaban_bundle_code, $list_pertanyaan['nilai']);
                                $id_nilai_peserta=Hashids::encode($list_pertanyaan['id_nilai_pertanyaan']);
                                $id_pertanyaan=Hashids::encode($list_pertanyaan['id_pertanyaan']);
                                for($i_variable = 0; $i_variable < $jumlah_variable; $i_variable++){
                                    //jumlah pertanyaan tiap variable
                                    $jlh_pertanyaan_variable=count($data_pertanyaan_statis[$i_variable]['daftar_pertanyaan']);
                                    for($i_pertanyaan = 0; $i_pertanyaan < $jlh_pertanyaan_variable; $i_pertanyaan++){
                                        if(isset($data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]["nilai_{$list_pertanyaan['id_pertanyaan']}"])){
                                            //$data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]["nilai_{$list_pertanyaan['id_pertanyaan']}"]=
                                            $jlh_pilihan_jawaban=count($data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]['pilihan_jawaban']);
                                            for($i_pilihan = 0; $i_pilihan < $jlh_pilihan_jawaban; $i_pilihan++){
                                                $current_id_jawaban=$data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]['pilihan_jawaban'][$i_pilihan]['id_jawaban_text'];
                                                //id_jawaban_bundle-point_jawaban
                                                $kode_jawaban=$current_id_jawaban.$pemisahString[0].$id_nilai_peserta.$pemisahString[1].$id_pertanyaan.$pemisahString[2].$id_pz_hashed."idZzh45h3d".$id_zonasi_satker_hashed;

                                                $text_jawaban=strtolower(str_replace(" ", "_", $data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]['pilihan_jawaban'][$i_pilihan]['jawaban_text']));
                                                $kode_jawaban_arr[$text_jawaban]=$kode_jawaban;

                                                $data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]['pilihan_jawaban'][$i_pilihan]['id_jawaban_text']=$kode_jawaban;
                                            }
                                            $data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]["nilai"]['text'] = $nilai;
                                            $data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]["nilai"]['id'] = strtolower(str_replace(" ", "_", $nilai)) === "belum_dinilai" ? null : $kode_jawaban_arr[strtolower(str_replace(" ", "_", $nilai))];
                                            
                                            unset($data_pertanyaan_statis[$i_variable]['daftar_pertanyaan'][$i_pertanyaan]["nilai_{$list_pertanyaan['id_pertanyaan']}"]);
                                        }

                                    }
                                    
                                }
                                $id_peserta_zonasi_before=$list_pertanyaan['id_peserta_zonasi'];
                                // $id_variable_before=$list_pertanyaan['id_variable'];
                                $a++;
                            }
                            $id_nilai_peserta_str.=Hashids::encode($list_pertanyaan['id_nilai_pertanyaan']);
                            $id_nilai_peserta_str.=Hashids::encode($id_periode+1);
                        }
                        $jumlah_nilai=count($edit_nilai);
                        for($i_locked_nilai=0;$i_locked_nilai<$jumlah_nilai;$i_locked_nilai++){
                            if((int)$edit_nilai[$i_locked_nilai] === 0){
                                $can_edit=Hashids::encode(0);
                                break;
                            }
                        }
                        $token_penilaian_periode=Hashids::encode($id_periode+=1)."-".Hashids::encode($id_zonasi_satker+=1)."-".$id_pz_hashed."-".$hashed_id_periode."-".$can_edit;
                        $signature_periode=generateSignature($token_penilaian_periode);
                    }else{
                        $msg="Tidak ada pertanyaan yang di setting";
                    }

                }else{
                    $msg="Data Peserta tidak ditemukan ";
                }
            }else{
                $msg="Data Zonasi Satker tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'can_edit'=>Hashids::decode($can_edit)[0] === 0 ? false : true,
                'token_penilaian'=>$token_penilaian_periode,
                'params'=>$id_nilai_peserta_str,
                'signature'=>$signature_periode,
                'data'=>$data_pertanyaan_statis,
                'peserta'=>$peserta,
                'keterangan'=>$keterangan,
            ];
        }

        public function getPertanyaanPeriodeSelected($id_periode, $id_pertanyaan_periode, $id_pertanyaan){
            $get_pertanyaan=Cache::store('redis')->remember("pertanyaan_periode_{$id_pertanyaan_periode}_{$id_periode}_{$id_pertanyaan}", "3600", function () use($id_periode, $id_pertanyaan_periode, $id_pertanyaan){
                return Trans_pertanyaan_periode::where('id', $id_pertanyaan_periode)
                                    ->where('id_periode', $id_periode)
                                    ->where('id_pertanyaan', $id_pertanyaan)
                                    ->first();
            });

            return $get_pertanyaan;
        }

        public function getJawabanBundle($id_jawaban, $point_jawaban){
            $get_jawaban=Cache::store('redis')->remember("bundle_jawaban_{$id_jawaban}", "3600", function () use($id_jawaban, $point_jawaban){
                return Tref_jawaban_bundle::where('id', $id_jawaban)
                                        ->where('point_jawaban', $point_jawaban)
                                        ->first();
            });

            return $get_jawaban;
        }

        public function saveJawaban($periode_id, $id_ref_pertanyaan, $id_pertanyaan_periode, $id_zonasi_satker, $id_jawaban, $point_jawaban, $id_nilai, $id_pz){
            $status=false;
            $get_zonasi_satker=$this->getZonasi($id_zonasi_satker);
            if(!is_null($get_zonasi_satker)){
                $id_periode=$get_zonasi_satker['id_periode'];
                $id_satker=$get_zonasi_satker['IdSatker'];
                $tgl_mulai_zonasi=$get_zonasi_satker['start_date'];
                $tgl_selesai_zonasi=$get_zonasi_satker['end_date'];
                $proses_id_zonasi=$get_zonasi_satker['proses_id'];
                if($this->validateZonasi($id_satker, $tgl_mulai_zonasi, $tgl_selesai_zonasi, $proses_id_zonasi)){
                   if((int)$id_periode === (int)$periode_id){
                    $get_pertanyaan_periode=$this->getPertanyaanPeriodeSelected($id_periode, $id_pertanyaan_periode, $id_ref_pertanyaan);
                    if(!is_null($get_pertanyaan_periode)){
                        $get_jawaban=$this->getJawabanBundle($id_jawaban, $point_jawaban);
                        if(!is_null($get_jawaban)){
                            $bundle_code_jawaban=$get_jawaban['bundle_code'];
                            $bundle_code_pertanyaan=$get_pertanyaan_periode['bundle_code_jawaban'];
                            if($bundle_code_jawaban === $bundle_code_pertanyaan){
                                $jumlah_peserta_zonasi=count($id_pz);
                                $parent_zonasi=$id_pz[0];
                                //check benarkan peserta ini ada di zonasi satker yang dimaksud
                                $jumlah_peserta_db=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)
                                                        ->whereIn('id', $id_pz)
                                                        ->count();
                                if($jumlah_peserta_db === $jumlah_peserta_zonasi){
                                    try{
                                        DB::beginTransaction();
                                        $affected_nilai=Trans_nilai_peserta_zonasi::where(function($w) use($id_nilai, $parent_zonasi, $id_pertanyaan_periode){
                                                                        $w->where('id', $id_nilai)
                                                                            ->where('id_peserta_zonasi', $parent_zonasi)
                                                                            ->where('locked', false)
                                                                            ->where('id_pertanyaan', $id_pertanyaan_periode);
                                                                    })
                                                                    ->orWhere(function($w2) use($parent_zonasi, $id_pertanyaan_periode){
                                                                        $w2->where('id_reference', $parent_zonasi)
                                                                            ->where('locked', false)
                                                                            ->where('id_pertanyaan', $id_pertanyaan_periode);
                                                                    })
                                                                    ->update(['nilai'=>$point_jawaban, 'updated_at'=>date('Y-m-d H:i:s')]);
                                        if($affected_nilai === $jumlah_peserta_zonasi){
                                            DB::commit();
                                            $status=true;
                                            $msg="Jawaban berhasil disimpan ".$affected_nilai;
                                        }else{
                                            throw new \Exception("Tidak dapat menyimpan jawaban anda");
                                        }
                                    }catch(\Exception $e){
                                        DB::rollBack();
                                        $msg=$e->getMessage();
                                    }
                                }else{
                                    $msg="Data peserta tidak ditemukan";
                                }
                            }else{
                                $msg="Data jawaban tidak konsisten";
                            }
                        }else{
                            $msg="Data Jawaban anda tidak valid";
                        }
                    }else{
                        $msg="Data Pertanyaan tidak ditemukan";
                    }
                   }else{
                    $msg="Data tidak valid";
                   }
                }else{
                    $msg="Zonasi sudah selesai";
                }

            }else{
                $msg="Data zonasi tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }


        private function generateNilaiPeserta($get_nilai, $id_periode){
            $nilai=$get_nilai->orderBy('id_peserta_zonasi', 'asc')->orderBy('id_pertanyaan', 'asc')->get();
            $id_peserta_zonasi_before=null;

            $get_pertanyaan_periode=$this->getPertanyaanPeriode($id_periode, 'getAll');
            foreach($get_pertanyaan_periode as $list_pertanyaan){
                $bobot["bobot_{$list_pertanyaan['id_pertanyaan_periode']}"]=$list_pertanyaan['bobot'];
            }
            $current_nilai_peserta=0;
            foreach($nilai as $list_nilai){
                if(is_null($id_peserta_zonasi_before) || $id_peserta_zonasi_before === (int)$list_nilai['id_peserta_zonasi']){
                    $nilai_temp=$list_nilai['nilai'] * $bobot["bobot_{$list_nilai['id_pertanyaan']}"] / 100;
                    $current_nilai_peserta+=$nilai_temp;
                    $id_peserta_zonasi_before=(int)$list_nilai['id_peserta_zonasi'];
                }
            }
            return  $current_nilai_peserta;
        }

        private function bobotJabatanPeriode($id_periode){
            $get_data=Cache::store("redis")->remember("bobot_periode_{$id_periode}", 3600*24*365, function () use($id_periode){
                return Trans_bobot_penilaian_periode::join('tref_bobot_penilaian as tbp', 'tbp.id', '=', 'trans_bobot_penilaian_periode.id_bobot_penilaian')
                                            ->select('tbp.id_jabatan_peserta', 'tbp.id_jabatan_penilai', 'trans_bobot_penilaian_periode.bobot')
                                            ->where('trans_bobot_penilaian_periode.id_periode', $id_periode)
                                            ->get();
            });
            $bobot=[];
            foreach($get_data as $list_data){
                $bobot["bobot_{$list_data['id_jabatan_peserta']}_{$list_data['id_jabatan_penilai']}"]=$list_data['bobot'];
            }

            return $bobot;
        }

        private function jumlahJabatanZonasiSatker($id_zona){

        }

        private function getDataPesertaObservee($id_observee){
            
        }

        private function countJabatanPenilaiSatker($id_zonasi_satker, $id_kelompok_jabatan_penilai, $id_observee_peserta){
            //get observee yang jabatannya menilai peserta
            $get_observee=Trans_observee::where('IdZonaSatker', $id_zonasi_satker)
                                            ->where('id_kelompok_jabatan', $id_kelompok_jabatan_penilai)
                                            ->get();
            $id_observee=[];
            foreach($get_observee as $list_observee){
                $id_observee[]=$list_observee['IdObservee'];
            }
            //gimana dengan yang plt, belum kehitung disini.

            $jumlah_penilai=Trans_peserta_zonasi::where('id_pegawai_peserta', $id_observee_peserta)
                                                ->whereIn('id_pegawai_penilai', $id_observee)
                                                ->where("id_zona_satker", $id_zonasi_satker)
                                                ->count();
            return $jumlah_penilai;     
        }

        private function generateNilaiObservee($id_zonasi_satker, $id_peserta_zonasi_arr, $id_periode, $current_nilai_peserta){
            $jabatan_peserta=Trans_peserta_zonasi::join('trans_observee as to', 'to.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_peserta')
                                            ->join('tref_jabatan_peserta as tjp', 'tjp.id_kelompok_jabatan', '=', 'to.id_kelompok_jabatan')
                                            ->join('trans_observee as to2', 'to2.IdObservee', '=', 'trans_peserta_zonasi.id_pegawai_penilai')
                                            ->join('tref_jabatan_peserta as tjp2', 'tjp2.id_kelompok_jabatan', '=', 'to2.id_kelompok_jabatan')
                                            ->select('tjp.id as id_jabatan_peserta', 'tjp.id_jabatan_gabungan as id_jabatan_gabungan_peserta', 'tjp2.id as id_jabatan_penilai', 'tjp2.id_jabatan_gabungan as id_jabatan_gabungan_penilai', 'trans_peserta_zonasi.id_jabatan_plt', 'trans_peserta_zonasi.nilai', 'tjp.id_kelompok_jabatan as id_kelompok_jabatan_peserta', 'tjp2.id_kelompok_jabatan as id_kelompok_jabatan_penilai', 'trans_peserta_zonasi.id_pegawai_peserta', 'trans_peserta_zonasi.id_pegawai_penilai')
                                ->whereIn('trans_peserta_zonasi.id', $id_peserta_zonasi_arr)
                                ->get();
            $bobot=$this->bobotJabatanPeriode($id_periode);
            $nilai_akhir=0;
            foreach($jabatan_peserta as $list_jabatan){
                $is_plt=false;
                $id_jabatan_peserta=$list_jabatan['id_jabatan_peserta'];
                if(is_null($list_jabatan['id_jabatan_plt'])){
                    $id_jabatan_penilai=$list_jabatan['id_jabatan_penilai'];
                    $id_kelompok_jabatan_penilai=$list_jabatan['id_kelompok_jabatan_penilai'];
                }else{
                    $id_jabatan_penilai=$list_jabatan['id_jabatan_plt'];
                    $get_kelompok_jabatan=Tref_jabatan_peserta::where("id", $id_jabatan_penilai)->first();
                    $id_kelompok_jabatan_penilai=$get_kelompok_jabatan["id_kelompok_jabatan"];
                    $is_plt=true;
                }

                if(!is_null($list_jabatan['id_jabatan_gabungan_peserta'])){
                    $id_jabatan_peserta=$list_jabatan['id_jabatan_gabungan_peserta'];
                }

                if(!is_null($list_jabatan['id_jabatan_gabungan_penilai']) && $is_plt === false){
                    $id_jabatan_penilai=$list_jabatan['id_jabatan_gabungan_penilai'];
                }

                $bobot_penilaian=$bobot["bobot_{$id_jabatan_peserta}_{$id_jabatan_penilai}"];
                $jumlah_penilai=$this->countJabatanPenilaiSatker($id_zonasi_satker, $id_kelompok_jabatan_penilai, $list_jabatan['id_pegawai_peserta']);
                if($is_plt === true){
                    $jumlah_penilai += 1;
                }
                if($jumlah_penilai === 0 && $id_jabatan_penilai === 1 && $id_jabatan_peserta === 1){
                    $get_penilaian=Trans_peserta_zonasi::where("id_pegawai_penilai", $list_jabatan['id_pegawai_penilai'])
                                                ->where("id_pegawai_peserta", $list_jabatan['id_pegawai_peserta'])
                                                ->first();
                    if(!is_null($get_penilaian)){
                        $jumlah_penilai=1;
                    }
                }
                $nilai_akhir+=(($current_nilai_peserta*$bobot_penilaian) / 100 ) / $jumlah_penilai;
                $id_observee_peserta=$list_jabatan['id_pegawai_peserta'];
            }

            return [
                'nilai_akhir'=>$nilai_akhir,
                'id_observee_peserta'=>$id_observee_peserta
            ];
        }


        public function lockJawaban($periode_id, $id_zonasi_satker, $id_pz, $id_nilai_peserta){
            $status=false;
            $get_zonasi_satker=$this->getZonasi($id_zonasi_satker);
            if(!is_null($get_zonasi_satker)){
                $id_periode=$get_zonasi_satker['id_periode'];
                $id_satker=$get_zonasi_satker['IdSatker'];
                $tgl_mulai_zonasi=$get_zonasi_satker['start_date'];
                $tgl_selesai_zonasi=$get_zonasi_satker['end_date'];
                $proses_id_zonasi=$get_zonasi_satker['proses_id'];
                if($this->validateZonasi($id_satker, $tgl_mulai_zonasi, $tgl_selesai_zonasi, $proses_id_zonasi)){
                    if((int)$id_periode === (int)$periode_id){
                        $jumlah_peserta_zonasi=count($id_pz);
                        
                        $peserta_db=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)
                                                        ->whereIn('id', $id_pz);
                        $jumlah_peserta_db=(clone $peserta_db)->count();
                        if($jumlah_peserta_db === $jumlah_peserta_zonasi){
                            try{
                                DB::beginTransaction();
                                $blm_jawab=false;
                                if($blm_jawab === false){
                                    $get_nilai=Trans_nilai_peserta_zonasi::whereIn('id', $id_nilai_peserta)
                                                            ->whereIn('id_peserta_zonasi', $id_pz)
                                                            ->where('locked', false);    
                                    
                                    //generate nilai
                                    $current_nilai_peserta=$this->generateNilaiPeserta(clone $get_nilai, $id_periode);                       
                                    $affected_locked=(clone $get_nilai)->update(['locked'=>true, 'updated_at'=>date('Y-m-d H:i:s')]);
                                    if($affected_locked === count($id_nilai_peserta)){
                                        //update nilai peserta zonasi satker (a menilai b)
                                        $update_nilai_peserta=(clone $peserta_db)->update(['nilai'=>$current_nilai_peserta]);
                                        if($update_nilai_peserta === count($id_pz)){
                                            $nilai_observee=$this->generateNilaiObservee($id_zonasi_satker, $id_pz, $id_periode, $current_nilai_peserta);
                                            //update nilai keseluruhan
                                            $get_observee=Trans_observee::where('IdObservee', $nilai_observee['id_observee_peserta'])
                                                                        ->lockForUpdate()    
                                                                        ->first();
                                            
                                            $total_nilai_terakhir=$get_observee['total_nilai'];
                                            $total_nilai_terakhir+=$nilai_observee['nilai_akhir']*20;
                                            $get_observee->total_nilai=$total_nilai_terakhir;
                                            $affected_observee = $get_observee->update();
                                            if($affected_observee === true){
                                                DB::commit();
                                                $status=true;
                                                $msg="Jawaban berhasil disimpan ";
                                            }else{
                                                throw new \Exception("Total nilai tidak berubah ".$affected_observee);
                                            }
                                        }else{
                                            throw new \Exception("Jawaban tidak bisa disimpan");
                                        }
                                    }else{
                                        throw new \Exception("Jawaban mungkin sudah dikonfirmasi ");
                                    }
                                }else{
                                    $msg="Pastikan seluruh pertanyaan sudah dijawab";
                                }
                            }catch(\Exception $e){
                                DB::rollBack();
                                $msg="Error Sistem Penilaian : ".$e->getMessage()." ".$e->getFile()." ".$e->getLine();
                            }
                        }else{
                            $msg="Data peserta tidak ditemukan";
                        }
                    }else{
                        $msg="Data jawaban tidak konsisten";
                    }
                }else{
                    $msg="Zonasi sudah selesai";
                }

            }else{
                $msg="Data zonasi tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

    }


?>