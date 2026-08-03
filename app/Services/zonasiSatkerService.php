<?php
    namespace App\Services;

use App\Jobs\SendWhatsappJob;
use App\Models\Log_msg;
use App\Models\Majelis_hakim;
use App\Models\Trans_jabatan_kosong;
use App\Models\Trans_observee;
use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_pegawai;
use App\Models\Tref_users;
use Vinkla\Hashids\Facades\Hashids;
    use App\Models\Tref_zonasi;
    use App\Models\Zonasi_satker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PDO;

    class zonasiSatkerService{

        protected $zonasiService;
        protected $penilaiaService;
        public function __construct(zonasiService $zonasi_service, penilaianService $penilaian_service)
        {
            $this->zonasiService=$zonasi_service;
            $this->penilaiaService=$penilaian_service;
        }

        public function listZonasiSatker($page, $id_satker){
            $status=false;
            $msg="";
            $data=[];

            if($page < 1){
                $page = 1;
            }
            $limit = 10;
            $total=Zonasi_satker::join('tref_zonasi as tz', function($join){
                                        $join->on('tz.IdZona', '=', 'trans_zonasi_satker.IdZona')
                                            ->whereRaw('tz.proses_id >= 2');
                                    })
                            ->where('IdSatker', $id_satker)->count();
            $jumlah_halaman=ceil($total / $limit);
            $skip=$page * $limit - $limit;

            $get_zonasi=Tref_zonasi::join('trans_zonasi_satker as tzs', function($join) use ($id_satker){
                                            $join->on('tzs.IdZona', '=', 'tref_zonasi.IdZona')
                                            ->where('tzs.IdSatker', $id_satker);
                                        })
                                    ->join('v_satker as vs', 'vs.IdSatker', '=', 'tzs.IdSatker')
                                    ->join('tref_tahapan_proses as thp', 'thp.id', '=', 'tref_zonasi.proses_id')
                                    ->skip($skip)->take($limit)
                                    ->select('tref_zonasi.*', 'vs.NamaSatker', 'tzs.IdZonaSatker', 'thp.proses')
                                    ->whereRaw('tref_zonasi.proses_id >= 2')
                                    ->get();
            if(!is_null($get_zonasi)){
                $status=true;
                $msg="Data Available";
                $x=0;
                foreach($get_zonasi as $list_zonasi){
                    $data[$x]['token_zonasi_satker']=Hashids::encode($list_zonasi['IdZonaSatker']);
                    $data[$x]['nama_zonasi']=$list_zonasi['nama_zona'];
                    $data[$x]['proses']=$list_zonasi['proses'];
                    $data[$x]['start_date']=date('d-m-Y', strtotime($list_zonasi['start_date']));
                    $data[$x]['end_date']=date('d-m-Y', strtotime($list_zonasi['end_date']));

                    $x++;
                }
            }else{
                $msg="Tidak ada Data";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'jumlah_halaman'=>$jumlah_halaman,
                'page'=>$page,
                'total'=>$total,
                'data'=>$data
            ];

        }
        
        public function detilZonasiSatker($id_zonasi_satker, $id_satker){
            $status=false;
            $msg="";
            $signature="";
            $data=[];
            $monitoring=false;
            $get_data=Zonasi_satker::join('tref_zonasi as tz', 'tz.IdZona', '=', 'trans_zonasi_satker.IdZona')
                        ->join('tref_tahapan_proses as thp', 'thp.id', '=', 'tz.proses_id')
                        ->where('trans_zonasi_satker.IdZonaSatker', $id_zonasi_satker)
                        ->where('trans_zonasi_satker.IdSatker', $id_satker)
                        ->select('tz.nama_zona', 'tz.start_date', 'tz.end_date', 'thp.proses', 'trans_zonasi_satker.IdZonaSatker', 'tz.proses_id')
                        ->first();
            if(!is_null($get_data)){
                $status=true;
                $msg="Data Available";
                $data['token_zonasi_satker']=Hashids::encode($get_data['IdZonaSatker']);
                $data['nama_zonasi']=$get_data['nama_zona'];
                $data['start_date']=date('d-m-Y', strtotime($get_data['start_date']));
                $data['end_date']=date('d-m-Y', strtotime($get_data['end_date']));
                $data['proses']=$get_data['proses'];
                if((int)$get_data['proses_id'] === 5 || (int)$get_data['proses_id'] === 6){
                    $monitoring=true;
                }
                $payload=json_encode(['payload'=>Hashids::encode($get_data['IdZonaSatker'])]);
                $secret=config('app.hmac_secret');
                $signature=hash_hmac('sha256', $payload, $secret);
            }else{
                $msg="Data tidak ditemukan";
            }
            $view['monitoring']=$monitoring;

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data,
                'view'=>$view
            ];
                        
        }

        public function getJabatanKosongSatker($id_zonasi_satker, $id_satker){
            $status=false;
            $data=[];
            $send_confirm=false;
            $not_sent=0;
            $not_filled=0;
            $get_jabatan_kososng=Trans_jabatan_kosong::join('tref_jabatan_peserta as a', 'a.id', '=', 'trans_jabatan_kosong.id_jabatan_kosong')
                                            ->leftJoin('trans_observee as to', 'to.IdObservee', '=', 'trans_jabatan_kosong.id_observee')
                                            ->leftJoin('tref_pegawai as tp', 'tp.id_pegawai', '=', 'to.IdPegawai')
                                            ->join('trans_zonasi_satker as tzs', function($join) use($id_satker){
                                                $join->on('tzs.IdZonaSatker', 'trans_jabatan_kosong.id_zonasi_satker')
                                                    ->where('tzs.IdSatker', $id_satker)
                                                    ->where('tzs.entry_job', true);
                                            })
                                            ->select('trans_jabatan_kosong.*', 'a.jabatan as jabatan_kosong', 'tp.nama_pegawai', 'to.NamaJabatan')
                                            ->where('trans_jabatan_kosong.id_zonasi_satker', $id_zonasi_satker)
                                            ->get();
            $total=$get_jabatan_kososng->count();
            if($total > 0){
                $status=true;
                $msg="Data Found";
                $x=0;
                foreach($get_jabatan_kososng as $list_jabatan_kosong){
                    $editable=true;
                    $data[$x]['token_jabatan_kosong']=Hashids::encode($list_jabatan_kosong['id']);
                    $data[$x]['jabatan_kosong']=$list_jabatan_kosong['jabatan_kosong']." (".$list_jabatan_kosong['bagian'].")";
                    $data[$x]['nama_pegawai']=$list_jabatan_kosong['nama_pegawai'];
                    $data[$x]['jabatan_pegawai']=$list_jabatan_kosong['NamaJabatan'];
                    if(!is_null($list_jabatan_kosong['id_observee']) && $list_jabatan_kosong['status'] === 0){
                        $not_sent+=1;
                    }
                    if((!is_null($list_jabatan_kosong['id_observee']) || is_null($list_jabatan_kosong['id_observee'])) && $list_jabatan_kosong['status'] === 1){
                        $editable=false;
                    }
                    $data[$x]['editable']=$editable;
                    $x++;
                }
                if((int)$total === (int)$not_sent){
                    $send_confirm=true;
                }
            }else{
                $msg="Tidak ada Jabatan Kosong. Mohon menunggu Satuan Kerja lain untuk mengisi jabatan kosong";
            }

            // $kelengkapan_majelis = $this->getKelengkapanMajelis($id_zonasi_satker);
            // $lengkap = $kelengkapan_majelis['lengkap'];
            // $jumlah_hakim = $kelengkapan_majelis['jlh_hakim'];
            // $data_majelis = $kelengkapan_majelis['data_majelis'];
            // $jlh_blm_masuk = $kelengkapan_majelis['jlh_blm_masuk'];
            // $confirmed = $kelengkapan_majelis['confirmed'];

            //send_confirm = Menampilkan tombol konfirmasi 
            if($send_confirm === true){
                $send_confirm = true;
            }else{
                $send_confirm = false;
                $status = true;
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'send_confirm'=>$send_confirm,
                'data'=>$data,
            ];
        }

        public function getMajelisHakim($id_zonasi_satker){
            $send_confirm = false;
            $kelengkapan_majelis = $this->getKelengkapanMajelis($id_zonasi_satker);
            $lengkap = $kelengkapan_majelis['lengkap'];
            $jumlah_hakim = $kelengkapan_majelis['jlh_hakim'];
            $data_majelis = $kelengkapan_majelis['data_majelis'];
            $jlh_blm_masuk = $kelengkapan_majelis['jlh_blm_masuk'];
            $confirmed = $kelengkapan_majelis['confirmed'];
            
            if($confirmed === false && $lengkap === true){
                $send_confirm = true;
            }
            
            return [
                'send_confirm'=>$send_confirm,
                'lengkap'=>$lengkap,
                'status_kelengkapan'=>$jlh_blm_masuk."/".$jumlah_hakim." Hakim karir belum masuk majelis",
                'list_majelis'=>$data_majelis,
                'jumlah_hakim'=>$jumlah_hakim
            ];
        }

        public function getKelengkapanMajelisPeriode($id_periode){
            $lengkap = false;
            
            $get_jumlah_hakim = Trans_observee::join("trans_zonasi_satker as tzs", "tzs.IdZonaSatker", "=", "trans_observee.IdZonaSatker")
                                                ->join("tref_zonasi as tz", "tz.IdZona", "tzs.IdZona")
                                                ->join("tref_tahun_penilaian as ttp", "ttp.IdTahunPenilaian", "=", "tz.IdTahunPenilaian")
                                                ->where("trans_observee.id_kelompok_jabatan", 30)
                                                ->where("tz.IdTahunPenilaian", $id_periode)
                                                ->select("ttp.IdTahunPenilaian as id_periode", "trans_observee.IdObservee")
                                                ->get();
            $jlh_hakim = $get_jumlah_hakim->count();
            $id_observee = [];

            foreach($get_jumlah_hakim as $list_hakim){
                $id_observee[]=$list_hakim['IdObservee'];
            }

            $get_majelis = Majelis_hakim::leftJoin("trans_observee as to", "to.IdObservee", "=", "tref_majelis_hakim.IdObservee")
                                        ->leftJoin("tref_pegawai as tp", "tp.id_pegawai", "=", "to.IdPegawai")
                                        ->where("tref_majelis_hakim.id_periode", $id_periode)
                                        ->where("tref_majelis_hakim.status", true)
                                        ->select("tref_majelis_hakim.*", "tp.nama_pegawai")
                                        ->orderBy("tref_majelis_hakim.nama_majelis")
                                        ->get();
            
            $id_observee_majelis = [];
           
            foreach($get_majelis as $list_majelis){
                $id_observee_majelis[] = $list_majelis['IdObservee'];
            }

            $compare_observee = array_values(array_diff($id_observee, $id_observee_majelis));
            //jumlah hakim belum masuk ke majelis
            $jumlah_blm_masuk = count($compare_observee);
            if($jumlah_blm_masuk === 0){
                $lengkap = true;
            }
            
            return [
                'lengkap'=>$lengkap,
                'jlh_hakim'=>$jlh_hakim,
                'jlh_blm_masuk'=>$jumlah_blm_masuk
            ];
        }

        public function getKelengkapanMajelis($id_zonasi_satker){
            $confirmed = true;
            $lengkap = false;
            $blm_confirm = 0;
            $get_jumlah_hakim = Trans_observee::join("trans_zonasi_satker as tzs", "tzs.IdZonaSatker", "=", "trans_observee.IdZonaSatker")
                                                ->join("tref_zonasi as tz", "tz.IdZona", "tzs.IdZona")
                                                ->join("tref_tahun_penilaian as ttp", "ttp.IdTahunPenilaian", "=", "tz.IdTahunPenilaian")
                                                ->where("trans_observee.id_kelompok_jabatan", 30)
                                                ->where("tzs.IdZonaSatker", $id_zonasi_satker)
                                                ->select("ttp.IdTahunPenilaian as id_periode", "trans_observee.IdObservee")
                                                ->get();
            $jlh_hakim = $get_jumlah_hakim->count();
            $id_observee = [];
            $id_periode = null;
            foreach($get_jumlah_hakim as $list_hakim){
                $id_observee[]=$list_hakim['IdObservee'];
                $id_periode = $list_hakim['id_periode'];
            }

            $get_majelis = Majelis_hakim::leftJoin("trans_observee as to", "to.IdObservee", "=", "tref_majelis_hakim.IdObservee")
                                        ->leftJoin("tref_pegawai as tp", "tp.id_pegawai", "=", "to.IdPegawai")
                                        ->where("tref_majelis_hakim.id_periode", $id_periode)
                                        ->where("tref_majelis_hakim.id_zonasi_satker", $id_zonasi_satker)
                                        ->select("tref_majelis_hakim.*", "tp.nama_pegawai")
                                        ->orderBy("tref_majelis_hakim.nama_majelis")
                                        ->get();
            
            $id_observee_majelis = [];
            $data_majelis = null;
            $nama_majelis_before = null;
            $x=0;
            $y=0;
            foreach($get_majelis as $list_majelis){
                $id_observee_majelis[] = $list_majelis['IdObservee'];
                if((int)$list_majelis['status'] === 0){
                    $blm_confirm +=1;
                }
                if($nama_majelis_before !== $list_majelis['nama_majelis']){
                    $y=0;
                    if(!is_null($nama_majelis_before)){
                         $x++;
                    }
                    $data_majelis[$x] = [
                        "token_majelis"=>Hashids::encode($list_majelis['id_periode'])."-".Hashids::encode($list_majelis['id_zonasi_satker'])."-".Crypt::encrypt($list_majelis['nama_majelis']),
                        "nama_majelis"=>$list_majelis['nama_majelis'],

                    ];
                }
                if((int)$list_majelis['IdObservee'] === 0){
                    $nama_pegawai = "Ketua Pengadilan";
                }elseif((int)$list_majelis['IdObservee'] === 1){
                    $nama_pegawai = "Wakil Ketua Pengadilan";
                }elseif((int)$list_majelis['IdObservee'] === 2){
                    $nama_pegawai = "Hakim Ad Hoc";
                }else{
                    $nama_pegawai = $list_majelis['nama_pegawai'];
                }
                $data_majelis[$x]['data_hakim'][$y] = [
                    'nama_pegawai'=>$nama_pegawai,
                    'status'=>(int)$list_majelis['status'] === 1 ? "Confirmed" : "Not Confirmed"
                ];
                $y++;
                $nama_majelis_before = $list_majelis['nama_majelis'];
            }

            $compare_observee = array_values(array_diff($id_observee, $id_observee_majelis));
            //jumlah hakim belum masuk ke majelis
            $jumlah_blm_masuk = count($compare_observee);
            if($jumlah_blm_masuk === 0){
                $lengkap = true;
            }

            if((int)$get_majelis->count() === (int)$blm_confirm){
                $confirmed = false;
            }
            
            return [
                'lengkap'=>$lengkap,
                'jlh_hakim'=>$jlh_hakim,
                'data_majelis'=>$data_majelis,
                'jlh_blm_masuk'=>$jumlah_blm_masuk,
                'confirmed'=>$confirmed
            ];
        }

        public function deleteMajelisHakim($id_periode, $id_zonasi_satker, $nama_majelis){
            $status = false;
            $get_data = Majelis_hakim::where("nama_majelis", $nama_majelis)
                        ->where('id_zonasi_satker', $id_zonasi_satker)
                        ->where('id_periode', $id_periode)
                        ->where('status', false);

            $jumlah = $get_data->count();
            if($jumlah === 3){
                if($get_data->delete()){
                    $status = true;
                    $msg = "Berhasil menghapus data";
                }else{
                    $msg = "Terjadi kesalahan sistem saat menghapus data";
                }
            }else{
                $msg = "Data majelis tidak dapat dihapus.";
            }

            return ['status'=>$status, 'msg'=>$msg];
        }

        public function testingFn(){
            $array_a = [0, 1, 2, 3, 4, 5];
            $array_b = [1, 2, 1, 3, 2];
            print_r(array_values(array_diff($array_a, $array_b)));
        }

        public function detilJabatanKosongSatker($id_jabatan_kosong, $id_satker){
            $status=false;
            $data=[];
            $signature=null;
            $get_data=Trans_jabatan_kosong::join('trans_zonasi_satker as tzs', function($join) use($id_satker){
                                                    $join->on('tzs.IdZonaSatker', '=', 'trans_jabatan_kosong.id_zonasi_satker')
                                                        ->where('tzs.IdSatker', $id_satker);
                                            })
                                            ->join('tref_jabatan_peserta as a', 'a.id', '=', 'trans_jabatan_kosong.id_jabatan_kosong')
                                            ->leftJoin('trans_observee as to', 'to.IdObservee', '=', 'trans_jabatan_kosong.id_observee')
                                            ->leftJoin('tref_pegawai as tp', 'tp.id_pegawai', '=', 'to.IdPegawai')
                                        ->select('trans_jabatan_kosong.*', 'a.jabatan as nama_jabatan_kosong', 'tp.nama_pegawai', 'to.NamaJabatan', 'tp.nip')
                                        ->where('trans_jabatan_kosong.id', $id_jabatan_kosong)
                                        ->first();
            if(!is_null($get_data)){
                $status_kirim=$get_data['status'];
                if((int)$status_kirim === 0){
                    $status=true;
                    $msg="Data Found";
                    $data['token_jabatan_kosong']=Hashids::encode($get_data['id']);
                    $data['jabatan_kosong']=$get_data['nama_jabatan_kosong'];
                    $data['nama_pegawai']=$get_data['nama_pegawai'];
                    $data['nip']=$get_data['nip'];
                    $data['jabatan_pegawai']=$get_data['NamaJabatan'];

                    $secret=config('app.hmac_secret');
                    $payload=json_encode(['payload'=>Hashids::encode($get_data['id'])]);
                    $signature=hash_hmac('sha256', $payload, $secret);
                }else{
                    $msg="Data ini tidak dapat  diubah lagi, karena sudah dikirimkan. Silahkan menghubungi Badilum untuk perubahan data";
                }

            }else{
                $msg="Data tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'signature'=>$signature,
                'data'=>$data
            ];

        }

        public function getDataPegawaiLocalByNIP($nip, $id_satker, $id_jabatan_kosong){
            $status=false;
            $data=[];
            $id_zonasi_satker=null;
            $get_jabatan_kosong=Trans_jabatan_kosong::where('id', $id_jabatan_kosong)->first();
            if(!is_null($get_jabatan_kosong)){
                $id_zonasi_satker=$get_jabatan_kosong['id_zonasi_satker'];
                $get_data=Tref_pegawai::join('trans_observee as to', function($join) use($id_zonasi_satker){
                                        $join->on('to.IdPegawai', '=', 'tref_pegawai.id_pegawai')
                                            ->where('to.IdZonaSatker', $id_zonasi_satker);
                                    })
                        ->join('trans_zonasi_satker as tzs', function($join) use($id_satker){
                                        $join->on('tzs.IdZonaSatker', '=', 'to.IdZonaSatker')
                                            ->where('tzs.IdSatker', $id_satker);
                                    })
                        ->where('tref_pegawai.nip', $nip)
                        ->select('tref_pegawai.nama_pegawai', 'tref_pegawai.nip', 'to.IdObservee', 'to.NamaJabatan')
                        ->first();
                
                if(!is_null($get_data)){
                    $status=true;
                    $msg="Data Found";
                    $data['nama_pegawai']=$get_data['nama_pegawai'];
                    $data['token_pegawai']=Hashids::encode($get_data['IdObservee']);
                    $data['nip']=$get_data['nip'];
                    $data['jabatan_pegawai']=$get_data['NamaJabatan'];
                }else{
                    $msg="NIP Pegawai tidak ditemukan";
                }
            }else{
                $msg="Data Jabatan Kosong tidak ditemukan";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
        }

        public function getHakimByNameNip($identitas, $id_satker, $id_zonasi_satker){
            $status = false;
            $data = null;
            $msg = "";
            $get_data = Tref_pegawai::join("trans_observee as to", function($join) use($id_zonasi_satker){
                                            $join->on("to.IdPegawai", "=", "tref_pegawai.id_pegawai")
                                                ->where("to.IdZonaSatker", $id_zonasi_satker)
                                                ->where("to.id_kelompok_jabatan", 30);
                                        })
                                    ->join("trans_zonasi_satker as tzs", function($join) use($id_satker){
                                        $join->on("tzs.IdZonaSatker", "=", "to.IdZonaSatker")
                                            ->where("tzs.IdSatker", $id_satker);
                                    })
                                    ->select("tref_pegawai.nama_pegawai", "to.IdObservee", "tref_pegawai.nip")
                                    ->where("tref_pegawai.nip", $identitas)
                                    ->orWhereRaw("tref_pegawai.nama_pegawai like '".$identitas."%'")
                                    ->first();
            
            if(!is_null($get_data)){
                $status = true;
                $msg = "Data Found";
                $data['nama'] = $get_data->nama_pegawai;
                $data['nip'] = $get_data->nip;
                $data['token_pegawai'] = Hashids::encode($get_data->IdObservee);
            }else{
                $msg = "Nama atau NIP tidak terdaftar. ".$id_satker;
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data
            ];
            
        }

        

        public function saveJabatanKosongSatker($nip, $id_satker, $id_jabatan_kosong){
            $status=false;
            $get_data=$this->getDataPegawaiLocalByNIP($nip, $id_satker, $id_jabatan_kosong);
            $status_get_nip=$get_data['status'];
            $msg=$get_data['msg'];
            $data=$get_data['data'];
            if($status_get_nip){
                $id_observee=Hashids::decode($data['token_pegawai']);
                if(empty($id_observee)){
                    return [
                        'status'=>false,
                        'msg'=>"Data Pegawai tidak valid. Fatal Error !"
                    ];
                }
                try{
                    $jabatan_kosong=Trans_jabatan_kosong::where('id', $id_jabatan_kosong)->first();
                    $status_kirim=$jabatan_kosong['status'];
                    if((int)$status_kirim === 1){
                        return [
                            'status'=>false,
                            'msg'=>"Tidak dapat menyimpan data. Jabatan ini sudah dikirim"
                        ];
                    }



                    DB::beginTransaction();
                        $id_zonasi_satker=$jabatan_kosong['id_zonasi_satker'];
                        $id_jabatan=$jabatan_kosong['id_jabatan_kosong'];
                        if(!is_null($jabatan_kosong)){
                            $jabatan_kosong->id_observee=$id_observee[0];
                            $jabatan_kosong->update();

                            Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)
                                            ->where('id_jabatan_plt', $id_jabatan)
                                            ->update(['id_pegawai_penilai' => $id_observee[0]]);
                        }else{
                            $msg="Data tidak ditemukan";
                        }
                    DB::commit();
                    $status=true;
                    $msg="Berhasil memperbaharui data Peserta Jabatan Kosong";
                }catch(\Exception $e){
                    DB::rollBack();
                    $msg=$e->getMessage();
                }
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function saveMejelisHakim($nama_majelis, $id_hakim_arr, $id_zonasi_satker){
            //0 : ketua
            //1 : wakil
            //2 : adhoc
            $status = false;
            $msg = "";
            try{
                $get_periode = Zonasi_satker::join("tref_zonasi as tz", "tz.IdZona", "=", "trans_zonasi_satker.IdZona")
                                            ->select("tz.IdTahunPenilaian")
                                            ->where("trans_zonasi_satker.IdZonaSatker", $id_zonasi_satker)
                                            ->first();
                $check_confirm = Majelis_hakim::where('id_zonasi_satker', $id_zonasi_satker)
                                                ->where('status', true)
                                                ->exists();
                if($check_confirm){
                    throw new \Exception("Sudah tidak bisa menambahkan Majelis");
                }
                if(!is_null($get_periode)){
                    $data = [];
                    $check_majelis = Majelis_hakim::where("nama_majelis", $nama_majelis)->where('id_zonasi_satker', $id_zonasi_satker)->exists();

                    if(!$check_majelis){
                        $check_komposisi = DB::table('tref_majelis_hakim')
                                            ->whereIn('IdObservee', $id_hakim_arr)
                                            ->where('id_zonasi_satker', $id_zonasi_satker)
                                            ->select('nama_majelis')
                                            ->groupBy('nama_majelis')->havingRaw('COUNT(DISTINCT IdObservee) > ?', [3])
                                            ->count();
                        if($check_komposisi < 3){
                            for($x=0;$x<count($id_hakim_arr);$x++){
                                $data[] = [
                                    'nama_majelis'=>$nama_majelis,
                                    'IdObservee'=>$id_hakim_arr[$x],
                                    'id_periode'=>$get_periode->IdTahunPenilaian,
                                    'id_zonasi_satker'=>$id_zonasi_satker,
                                    'status'=>false,
                                    'created_at'=>date("Y-m-d H:i:s")
                                ];
                            }
                            DB::table("tref_majelis_hakim")->insert($data);
                            $status = true;
                            $msg = "Berhasil menyimpan data Majelis ".$nama_majelis;
                        }else{
                            $msg = "Komposisi Majelis sudah ada";
                        }
                    }else{
                        $msg = "Nama Majelis ".$nama_majelis." di periode ini sudah ada";
                    }
                }else{
                    $msg = "Data Zonasi tidak ditemukan";
                }

            }catch(\Exception $e){
                $msg = $e->getMessage();
            }

            return ['status'=>$status, 'msg'=>$msg];
        }

        

        public function checkJabatanKosongZonasi($id_zonasi){
            $check_data=Trans_jabatan_kosong::where('id_zonasi', $id_zonasi)
                        ->whereRaw('id_observee is null')
                        ->where('status', false)
                        ->count();
            return $check_data;
        }

        public function sendConfirmMajelisHakim($id_zonasi_satker){
            $status = false;
            $msg = "";
            $id_periode = null;
            $check_kelengkapan = $this->getKelengkapanMajelis($id_zonasi_satker);
            $lengkap = $check_kelengkapan['lengkap'];
            if($lengkap === true){
                $check = Majelis_hakim::where("id_zonasi_satker", $id_zonasi_satker)->where('status', false)->exists();
                if($check){
                    Majelis_hakim::where("id_zonasi_satker", $id_zonasi_satker)->update(['status'=>true]);
                    $get_periode = Majelis_hakim::where("id_zonasi_satker", $id_zonasi_satker)->select("id_periode")->first();
                    $status = true;
                    $msg = "Data Majelis berhasil dikonfirmasi";
                    $id_periode = $get_periode->id_periode;

                    //check kelengkapan seluruhnya
                    $check_lengkap_all = $this->getKelengkapanMajelisPeriode($id_periode);
                    $lengkap = $check_lengkap_all['lengkap'];
                    if($lengkap){
                        $msg_wa = getWAMsg("majelis_lengkap", "");
                        $get_admin_badilum=Tref_users::join('tref_pegawai as tp', 'tp.id_pegawai', '=', 'tref_users.IdPegawai')
                                    ->select("tp.no_hp", "tp.nama_pegawai", "tp.nip")
                                    ->where("tref_users.uname", "097450")
                                    ->first();
                        if(!is_null($get_admin_badilum)){
                            $no_hp=$get_admin_badilum['no_hp'];
                            $nama_admin=$get_admin_badilum['nama_pegawai'];
                            $nip_admin=$get_admin_badilum['nip'];
                            $send_wa=sendWa($msg_wa, $nip_admin, $nama_admin, $no_hp);
                        }else{
                            $msg_wa="Urgent !!!.\n\nData Admin Badilum tidak memliki no handphone atau data nya tidak ada. Silahkan lakukan trace data pada data admin badilum.";
                            $send_wa=sendWa($msg_wa, "199306242019031004", "Fandy Juniario Simorangkir", "085880037948");
                            $msg_log_sent="Data Admin badilum tidak memiliki no handphone atau data nya belum ada. Silahkan lakukan trace data admin badilum";
                            // $this->zonasiService->saveLog($id_zonasi, "jobs_notif", $msg_log_sent, "error");
                        }
                    }

                }else{
                    $msg = "Data Majelis sudah dikirimkan";
                }
            }else{
                $msg = "Masih ada hakim karir yang belum masuk ke majelis";
            }
            return ['status'=>$status, 'msg'=>$msg, 'id_periode'=>$id_periode];
        }

        public function generateJobSendWA($id_zonasi, $id_periode, $username){
            $send_notif = false;
            $status = false;
            $check_data=$this->checkJabatanKosongZonasi($id_zonasi);
            $get_majelis = $this->getKelengkapanMajelisPeriode($id_periode);
            $lengkap = $get_majelis['lengkap'];
            $blm_masuk = $get_majelis['jlh_blm_masuk'];
            $jlh_hakim = $get_majelis['jlh_hakim'];

            if($check_data === 0 && $lengkap === true){
                $get_zonasi=Tref_zonasi::where('IdZona', $id_zonasi)->first();
                if(!is_null($get_zonasi)){
                    $start_date=date("Y-m-d", strtotime($get_zonasi['start_date']));
                    $end_date=date("Y-m-d", strtotime($get_zonasi['end_date']));
                    $date_now=date("Y-m-d");
                    if($date_now < $start_date){
                        $proses_id=4;
                    }else if($date_now >= $start_date && $date_now <= $end_date){
                        $proses_id = 5;
                        $send_notif=true;
                    }else if($date_now > $start_date && $date_now > $end_date){
                        $proses_id=6;
                    }else{
                        $proses_id=null;
                    }

                    if(!is_null($proses_id)){
                        $current_proses_id=(int)$get_zonasi['proses_id'];
                        if($current_proses_id < (int)$proses_id){
                            $get_zonasi->proses_id=$proses_id;
                            $get_zonasi->diperbarui_oleh=$username;
                            $get_zonasi->diperbarui_tgl=date("Y-m-d H:i:s");
                            $get_zonasi->update();

                            if($send_notif){
                                $create_job=$this->createJobSendWA($id_zonasi);
                                $status_job=$create_job['status'];
                                $msg_job=$create_job['msg'];
                                $status_log="error";
                                if($status_job === true){
                                    $status_log="finished";
                                    $msg_job="Berhasil mengirimkan Pesan Whatsapp ke Admin Badilum untuk mengirimkan Notifikasi";
                                }
                                $this->zonasiService->saveLog($id_zonasi, "send_notif", $msg_job, $status_log);
                            }

                        }else{
                            throw new \Exception("Tahapan Proses tidak boleh Mundur");
                        }
                    }else{
                        throw new \Exception('Proses Tahapan Zonasi tidak dapat didefinisikan');
                    }
                }else{
                    throw new \Exception('Data zonasi tidak ditentukan');
                }
            }
        }

        public function sendConfirmJabatanKosong($id_zonasi_satker){
            $send_notif=false;
            $status=false;
            $not_filled=0;
            $sent=0;
            $get_data=Trans_jabatan_kosong::where('id_zonasi_satker', $id_zonasi_satker)
                                    ->get();
            $jumlah_jabatan_kosong=$get_data->count();
            $id_zonasi=null;

            if($jumlah_jabatan_kosong > 0){
                foreach($get_data as $list_jabatan_kosong){
                    if(is_null($list_jabatan_kosong['id_observee'])){
                        $not_filled+=1;
                    }
                    if((int)$list_jabatan_kosong['status'] === 1){
                        $sent+=1;
                    }
                    $id_zonasi=$list_jabatan_kosong['id_zonasi'];
                }

                if($not_filled > 0){
                    $msg="Masih ada Jabatan yang belum diisi";
                    return ['status'=>false, 'msg'=>$msg];
                }
                if((int)$sent === (int)$jumlah_jabatan_kosong){
                    $msg="Seluruh data sudah dikirim";
                }else if(((int)$sent === 0 || (int)$sent < (int)$jumlah_jabatan_kosong)  && (int)$jumlah_jabatan_kosong > 0){    
                    $affected=Trans_jabatan_kosong::where('id_zonasi_satker', $id_zonasi_satker)->update(['status'=>true]);
                    if($affected > 0){
                        $status=true;
                        $msg="Berhasil mengirimkan ".$affected." data";
                    }else{
                        $msg = "Tidak ada data jabatan yang diubah";
                    } 
                
                }else{
                    $msg="Tidak ada data yang dikirimkan";
                }
            }else{
                $status = true;
                $msg="Tidak ada Jabatan Kosong";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'id_zonasi'=>$id_zonasi
            ];
        }

        public function createJobSendWA($id_zonasi){
            $status=false;
            $data=[];
            $get_zonasi_satker=Zonasi_satker::where('IdZona', $id_zonasi)->get();
            $id_zonasi_satker=[];
            foreach($get_zonasi_satker as $list_zonasi_satker){
                $id_zonasi_satker[]=$list_zonasi_satker['IdZonaSatker'];
            }

            $get_data_pegawai=Trans_observee::join('tref_pegawai as tp', 'tp.id_pegawai', '=', 'trans_observee.IdPegawai')
                                        ->select('tp.no_hp', 'tp.nama_pegawai', 'tp.nip', 'trans_observee.endpoint')
                                        ->whereIn('trans_observee.IdZonaSatker', $id_zonasi_satker)
                                        ->get();
            $total_data=$get_data_pegawai->count();
            if($total_data > 0){
                $i=0;
                foreach($get_data_pegawai as $list_data_pegawai){
                    $msg_wa = getWAMsg("notif_penilaian", $list_data_pegawai['nama_pegawai'], $list_data_pegawai['endpoint']);
                    $no_hp = $list_data_pegawai['no_hp'];
                    $nama_pegawai=$list_data_pegawai['nama_pegawai'];
                    $nip_pegawai=$list_data_pegawai['nip'];

                    dispatch(new SendWhatsappJob($nip_pegawai, $nama_pegawai, $no_hp, $msg_wa, $id_zonasi))
                        ->onQueue("send_wa_peserta_".$id_zonasi)
                         ->delay(now()->addMilliseconds(rand(300, 1500)));
                }
                $msg_log_jobs=$get_data_pegawai->count()." Jobs untuk kirim pesan whatsapp berhasil disimpan";
                $this->zonasiService->saveLog($id_zonasi, "jobs_notif", $msg_log_jobs, "prepare");
                

                //Kirim Pesan ke admin badilum untuk menekan tombol kirim Pesan
                $msg_wa_badilum=getWAMsg("notif_send_penilaian_badilum", "", "");
                $get_admin_badilum=Tref_users::join('tref_pegawai as tp', 'tp.id_pegawai', '=', 'tref_users.IdPegawai')
                                    ->select("tp.no_hp", "tp.nama_pegawai", "tp.nip")
                                    ->where("tref_users.uname", "097450")
                                    ->first();
                if(!is_null($get_admin_badilum)){
                    $no_hp=$get_admin_badilum['no_hp'];
                    $nama_admin=$get_admin_badilum['nama_pegawai'];
                    $nip_admin=$get_admin_badilum['nip'];
                    $send_wa=sendWa($msg_wa_badilum, $nip_admin, $nama_admin, $no_hp);
                }else{
                    $msg_wa="Urgent !!!.\n\nData Admin Badilum tidak memliki no handphone atau data nya tidak ada. Silahkan lakukan trace data pada data admin badilum.";
                    $send_wa=sendWa($msg_wa, "199306242019031004", "Fandy Juniario Simorangkir", "085880037948");
                    $msg_log_sent="Data Admin badilum tidak memiliki no handphone atau data nya belum ada. Silahkan lakukan trace data admin badilum";
                    $this->zonasiService->saveLog($id_zonasi, "jobs_notif", $msg_log_sent, "error");
                }
                $status=$send_wa['status'];
                $msg=$send_wa['msg'];
            }else{
                $msg="Tidak ada data Pegawai untuk observee ini";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
            
        }

        public function progressJobsNotif($id_zonasi){
            $status=false;
            $get_data=Log_msg::where('category', 'jobs_notif')
                            ->where('data_id', $id_zonasi);
            $log_progress=(clone $get_data)->where('status', 'progress')
                            ->first();
            if(!is_null($log_progress)){
                $msg=$log_progress['msg'];
                $get_finished=(clone $get_data)->where('status', 'finished')->first();
                if(!is_null($get_finished)){
                    $status=true;
                    $msg.="\n";
                    $msg.=$get_finished['msg'];
                }
            }else{
                $msg="Menunggu mengirimkan pesan ...";
            }

            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }


        public function monitoringZonasiSatker($id_zonasi_satker, $id_satker_ctlr, $limit, $page, $refresh){
            $status=false;
            $payload="";
            $jumlah_halaman = 0;
            $sudah_menilai=0;
            $total=0;
            $percentage=0;
            $ratio="0 / 0";
            $data=[];
            $send_to_badilum=false; // false artinya tidak dalam state untuk mengirim ke badilum
            $zonasi_satker=$this->penilaiaService->getZonasi($id_zonasi_satker);
            $signature="";
            $msg="";
            $blm_kirim_badilum=true;
            if(!is_null($zonasi_satker)){
                $id_satker=$zonasi_satker['IdSatker'];
                $tgl_mulai_zonasi=$zonasi_satker['start_date'];
                $tgl_selesai_zonasi=$zonasi_satker['end_date'];
                $proses_id_zonasi=$zonasi_satker['proses_id'];
                if((int)$id_satker === (int)$id_satker_ctlr){
                    $peserta_blm_nilai=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)
                                                    ->where('nilai', 0)
                                                    ->count();
                    if($this->penilaiaService->validateZonasi($id_satker, $tgl_mulai_zonasi, $tgl_selesai_zonasi, $proses_id_zonasi)){
                        if((int)$zonasi_satker['send_to_badilum'] === 0){
                            $send_to_badilum=true;
                        }
                    }else{
                        $msg="Zonasi telah selesai";
                    }
                    
                    $total_penilai=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)
                                ->distinct('id_pegawai_penilai')        
                                ->count();

                    $total=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)->count();
                    $sudah_menilai=$total - $peserta_blm_nilai;
                    $percentage=$sudah_menilai / $total * 100;
                    // $ratio = $sudah_menilai." / ".$total;
                    
                    $jumlah_halaman=ceil($total_penilai / $limit);
                    if($page > $jumlah_halaman){
                        $page = 1;
                    }
                    $skip= $page * $limit - $limit;
                    if($refresh === true){
                        Cache::store('redis')->forget("peserta_zonasi_{$id_zonasi_satker}_{$skip}_{$limit}");
                        Cache::store('redis')->forget("zonasi_satker_{$id_zonasi_satker}");
                    }
                    $get_data=$this->getListPesertaZonasiSatker($id_zonasi_satker, $skip, $limit);
                    $jumlah_data=count($get_data);
                    if($jumlah_data > 0){
                        $status=true;
                        $x=0;
                        foreach($get_data as $list_data){
                            $data[$x]['nama_penilai'] = $list_data->nama_pegawai_penilai;
                            $data[$x]['jumlah_selesai'] = $list_data->jumlah_selesai;
                            $data[$x]['jumlah_dinilai'] = $list_data->jumlah_dinilai;
                            $data[$x]['percentage']=(int)$list_data->jumlah_selesai / (int)$list_data->jumlah_dinilai  * 100;
                            $x++;
                        }
                        $payload=Hashids::encode($id_zonasi_satker)."-".Hashids::encode($id_satker_ctlr)."-".Hashids::encode($total);
                        $signature=generateSignature($payload);
                    }else{
                        $msg="Data tidak ditemukan :1";
                    }
                }else{
                    $msg="Data tidak valid";
                }
            }else{
                $msg="Data tidak ditemukan :2";
            }

            return [
                'status'=>$status,
                'msg'=>$msg,
                'percentage'=>$percentage,
                'jumlahHalaman'=>$jumlah_halaman,
                'page'=>$page,
                'data'=>$data,
                'send_to_badilum'=>$send_to_badilum,
                'sudah_menilai'=>$sudah_menilai,
                'total_penilaian'=>$total,
                'token_monitoring'=>$payload,
                'signature'=>$signature

            ];
        }   
        public function getListPesertaZonasiSatker($id_zonasi_satker, $skip, $limit){
            $get_data_selesai=DB::table("trans_peserta_zonasi as tpz")
                            ->select("tpz.id_pegawai_peserta", DB::raw('COUNT(id_pegawai_penilai) as jumlah_selesai'))
                            ->where('tpz.id_zona_satker', $id_zonasi_satker)
                            ->where('tpz.nilai', '>', 0)
                            ->groupBy('tpz.id_pegawai_peserta');

            $get_data_dinilai=DB::table("trans_peserta_zonasi as tpz")
                            ->select("tpz.id_pegawai_peserta", DB::raw('COUNT(id_pegawai_penilai) as jumlah_dinilai'))
                            ->where("tpz.id_zona_satker", $id_zonasi_satker)
                            ->groupBy('tpz.id_pegawai_peserta');
                            
            Cache::store('redis')->forget("peserta_zonasi_{$id_zonasi_satker}_{$skip}_{$limit}");
            $get_data=Cache::store('redis')->remember("peserta_zonasi_{$id_zonasi_satker}_{$skip}_{$limit}", 3600*24*3, function() use($get_data_selesai, $get_data_dinilai, $skip, $limit){
                return DB::table("trans_peserta_zonasi as tpz")
                        ->join('trans_observee as toe1', 'toe1.IdObservee', '=', 'tpz.id_pegawai_peserta')
                        ->join('tref_pegawai as tp1', 'tp1.id_pegawai', '=', 'toe1.IdPegawai')
                        ->leftJoinSub($get_data_selesai, 'tpz2', function($join){
                            $join->on("tpz2.id_pegawai_peserta", "=", "tpz.id_pegawai_peserta");
                        })
                        ->joinSub($get_data_dinilai, 'tpz3', function($join){
                            $join->on('tpz3.id_pegawai_peserta', '=', 'tpz.id_pegawai_peserta');
                        })
                        ->select("tp1.nama_pegawai as nama_pegawai_penilai", 
                            DB::raw('COALESCE(tpz2.jumlah_selesai, 0) as jumlah_selesai'),
                            'tpz3.jumlah_dinilai'
                        )
                        ->skip($skip)
                        ->take($limit)
                        ->groupBy("tpz.id_pegawai_peserta", "tp1.nama_pegawai", "tpz3.jumlah_dinilai", "tpz2.jumlah_selesai")
                        ->get();
            });

            return $get_data->toArray();
        }

        public function sendPenilaianToBadilum($id_zonasi_satker, $id_satker_ctlr, $jumlah_penilaian){
            $status=false;
            $get_zonasi=$this->penilaiaService->getZonasi($id_zonasi_satker);
            if(!is_null($get_zonasi)){
                $id_satker=$get_zonasi['IdSatker'];
                $tgl_mulai_zonasi=$get_zonasi['start_date'];
                $tgl_selesai_zonasi=$get_zonasi['end_date'];
                $proses_id_zonasi=$get_zonasi['proses_id'];
                if((int)$id_satker === (int)$id_satker_ctlr){
                    if($this->penilaiaService->validateZonasi($id_satker, $tgl_mulai_zonasi, $tgl_selesai_zonasi, $proses_id_zonasi)){
                        $peserta_blm_nilai=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)->where('nilai', 0)->exists();
                        if(!$peserta_blm_nilai){
                            try{
                                DB::beginTransaction();
                                    $update_peserta=Trans_peserta_zonasi::where('id_zona_satker', $id_zonasi_satker)
                                                ->update(['status'=> true]);
                                    if($update_peserta === (int)$jumlah_penilaian){
                                        #ubah status send_to_badilum observee menjadi true
                                        $update_observee=Trans_observee::where("IdZonaSatker", $id_zonasi_satker)
                                                    ->update(['send_to_badilum' => true]);
                                        #ubah status pengiriman nilai satker menjadi true
                                        $get_zonasi_satker=Zonasi_satker::where("IdZonaSatker", $id_zonasi_satker)->first();
                                        $get_zonasi_satker->kirim_penilaian=true;
                                        $get_zonasi_satker->update();
                                        DB::commit();
                                        $status=true;
                                        $msg="Berhasil mengirimkan penilaian ke Badilum";
                                        Cache::store("redis")->forget("zonasi_satker_{$id_zonasi_satker}");
                                    }else{
                                         throw new \Exception("Data Penilaian tidak sesuai ".$update_peserta." : ".$jumlah_penilaian);
                                    }
                            }catch(\Exception $e){
                                DB::rollBack();
                                $msg=$e->getMessage();
                            }
                        }else{
                            $msg="Masih ada Peserta yang belum dinilai. Silahkan diisi terlebih dahulu";
                        }
                    }else{
                        $msg="Zonasi ini sudah tidak bisa dikirimkan lagi";
                    }
                }else{
                    $msg="Data Penilaian tidak aktif :1";
                }
                // $get_data=Trans_peserta_zonasi::where("id_zonasi")
            }else{
                $msg="Data penilaian tidak valid :2";
            }
            return [
                'status'=>$status,
                'msg'=>$msg
            ];
        }

        public function getZonasiSatkerByPeriodeZonasi($id_periode, $id_zonasi){
            $data=[];$x=0;
            Cache::store('redis')->forget("zonasi_satker_{$id_periode}_{$id_zonasi}");
            $get_data=Cache::store('redis')->remember("zonasi_satker_{$id_periode}_{$id_zonasi}", 3600*24*365, function() use($id_zonasi, $id_periode){
                return Zonasi_satker::join('tref_zonasi as tz', 'tz.IdZona', '=', 'trans_zonasi_satker.IdZona')
                                    ->join('tref_tahun_penilaian as tp', 'tp.IdTahunPenilaian', '=', 'tz.IdTahunPenilaian')
                                    ->join('v_satker as vs', 'vs.IdSatker', '=', 'trans_zonasi_satker.IdSatker')
                                    ->select('trans_zonasi_satker.IdZonaSatker', 'vs.NamaSatker as nama_satker')
                                    ->where("tz.IdZona", $id_zonasi)
                                    ->where("tz.IdTahunPenilaian", $id_periode)
                                    ->get();
            });
            foreach($get_data as $satker){
                $data[$x]['token_zonasi_satker']=Hashids::encode($satker['IdZonaSatker']);
                $data[$x]['nama_satker']=$satker['nama_satker'];
                $x++;
            }

            return $data;
        }
    }
?>