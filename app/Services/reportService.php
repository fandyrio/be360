<?php
    namespace App\Services;

use App\Models\Trans_nilai_peserta_zonasi;
use App\Models\Trans_observee;
use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_jabatan_peserta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Vinkla\Hashids\Facades\Hashids;

    class reportService{
        protected $penilaianService;

        public function __construct(penilaianService $penilaian_service){
            $this->penilaianService=$penilaian_service;
        }   
        public function generateDataReport($id_zonasi_satker, $id_zonasi, $id_periode_ctrl, $refesh){
            $data=[];
            $msg="";
            $status=false;
            $get_zonasi=$this->penilaianService->getZonasi($id_zonasi_satker);
            $id_periode=$get_zonasi['id_periode'];
            if((int)$id_periode_ctrl === $id_periode){
                /** @var \Illuminate\Support\Collection $get_jabatan_periode */
                // if($refesh === true){
                    Cache::store('redis')->forget("jabatan_periode_{$id_periode}");
                    Cache::store('redis')->forget("report_periode_zs_satker_{$id_periode}_{$id_zonasi_satker}_{$id_zonasi}");
                // }
                $get_jabatan_periode=Cache::store('redis')->remember("jabatan_periode_{$id_periode}", 3600*24*24, function() use($id_periode){
                    return DB::table("tref_jabatan_peserta as tjp")
                                ->whereRaw("id IN (SELECT DISTINCT(id_jabatan_peserta) from tref_mapping_jabatan as tmj 
                                            JOIN trans_mapping_jabatan_periode as tmjp on tmjp.id_mapping_jabatan = tmj.id where tmjp.id_periode = {$id_periode})"
                                )
                                ->get();

                });

                $jabatan_cols=$get_jabatan_periode->map(function($row){
                    return "SUM(
                        CASE 
                            WHEN toe.id_kelompok_jabatan = {$row->id_kelompok_jabatan} 
                                THEN 1
                            WHEN tjp.id_jabatan_gabungan is not null and tjp.id_jabatan_gabungan = {$row->id}
                                THEN 1 
                            ELSE 0 
                        END) AS `{$row->jabatan}`";
                })->implode(', ');

                $sql="
                    SELECT toe2.IdObservee as id_observee_peserta, tp.id as id_pegawai_peserta, tp.nama_pegawai, 
                        CASE
                            when toe2.NamaJabatan = 'Panitera Muda'
                                THEN 
                                    toe2.bagian
                                else
                                    toe2.NamaJabatan
                            end 
                            as jabatan, 
                        $jabatan_cols, MAX(toe2.total_nilai) as nilai
                    from trans_peserta_zonasi as tpz
                    JOIN trans_observee as toe on toe.IdObservee = tpz.id_pegawai_penilai
                    JOIN tref_jabatan_peserta as tjp on tjp.id_kelompok_jabatan = toe.id_kelompok_jabatan
                    JOIN trans_observee as toe2 on toe2.IdObservee = tpz.id_pegawai_peserta
                    JOIN tref_pegawai as tp on tp.id_pegawai = toe2.IdPegawai
                    where tpz.id_zonasi = ? and id_zona_satker = ?
                    group by tp.nama_pegawai, toe2.NamaJabatan, toe2.bagian,  toe2.IdObservee, tp.id
                    order by nilai desc
                ";

                $data_report=Cache::store('redis')->remember("report_periode_zs_satker_{$id_periode}_{$id_zonasi_satker}_{$id_zonasi}", 3600*24*365, function() use($sql, $id_zonasi, $id_zonasi_satker){
                    $data = DB::select($sql, [$id_zonasi, $id_zonasi_satker]);
                    foreach($data as $list_data){
                        $token_o=Crypt::encrypt($list_data->id_observee_peserta);
                        $token_p=Hashids::encode($list_data->id_pegawai_peserta);
                        $endpoint=$token_o."063atam".$token_p;
                        $list_data->endpoint=$endpoint;
                        unset($list_data->id_observee_peserta, $list_data->id_pegawai_peserta);
                    }
                    return $data;
                });
                $status=true;
                $msg="Data ditemukan";
            }else{
                $msg="Data tidak konsisten";
            }


            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data_report
            ];
        }

        public function reportIndividualBadilum($id_periode, $id_observee){
            #1. Ambil data personal
            $data_personal = null;
            $data_jlh_penilai=[];
            $data_report=null;
            $data_avg=null;
            $status=false;
            $get_data_personal=Trans_observee::join("tref_pegawai as tp", "tp.id_pegawai", "trans_observee.IdPegawai")
                            ->select("trans_observee.NIPBaru as nip", "trans_observee.NamaJabatan as jabatan", "trans_observee.bagian as bagian",  "trans_observee.total_nilai as nilai_akhir", "tp.nama_pegawai", "tp.foto_pegawai", "trans_observee.IdZonaSatker")
                            ->where("trans_observee.IdObservee", $id_observee)
                            ->first();
            // var_dump($get_data_personal['IdZonaSatker']);
            if(!is_null($get_data_personal)){
                #2. Statistik Jumlah Jabatan Penilaian
                $sub=Trans_observee::join("tref_jabatan_peserta as tjp", "tjp.id_kelompok_jabatan", "trans_observee.id_kelompok_jabatan")
                                ->where("trans_observee.IdZonaSatker", $get_data_personal['IdZonaSatker'])
                                ->select("tjp.jabatan", DB::raw("COUNT(trans_observee.id_kelompok_jabatan) as total_orang"))
                                ->groupBy("tjp.jabatan");
                // var_dump($sub->get());
                $get_data_jlh_penilai=Trans_peserta_zonasi::join("trans_observee as to", "to.IdObservee", "trans_peserta_zonasi.id_pegawai_penilai")
                                    ->join("trans_observee as to2", "to2.IdObservee", "trans_peserta_zonasi.id_pegawai_peserta")
                                    ->join("trans_zonasi_satker as tzs", "tzs.IdZonaSatker", "to.IdZonaSatker")
                                    ->join("tref_zonasi as tz", "tz.IdZona", "tzs.IdZona")
                                    ->join("tref_tahun_penilaian as ttp", "ttp.IdTahunPenilaian", "tz.IdTahunPenilaian")
                                    ->join("tref_jabatan_peserta as tjp", "tjp.id_kelompok_jabatan", "to.id_kelompok_jabatan")
                                    ->join("tref_jabatan_peserta as tjp2", "tjp2.id_kelompok_jabatan", "to2.id_kelompok_jabatan")
                                    ->join("tref_mapping_jabatan as tmj", function($join){
                                        $join->on("tmj.id_jabatan_penilai", "tjp.id")
                                            ->on("tmj.id_jabatan_peserta", "tjp2.id")
                                            ->where("tmj.active", true);
                                    })
                                    ->join("trans_mapping_jabatan_periode as tmjp", function($join){
                                        $join->on("tmjp.id_mapping_jabatan", "=", "tmj.id")
                                        ->on("tmjp.id_periode", "=", "ttp.IdTahunPenilaian");
                                    })
                                    ->joinSub($sub, "jumlah_orang", function($join){
                                        $join->on("jumlah_orang.jabatan", "tjp.jabatan");
                                    })
                                    ->where("trans_peserta_zonasi.id_pegawai_peserta", $id_observee)
                                    ->where("ttp.IdTahunPenilaian", $id_periode)
                                    ->selectRaw("tjp.jabatan, COUNT(to.id_kelompok_jabatan) as jumlah_jabatan_penilai, jumlah_orang.total_orang, tmjp.threshold")
                                    ->groupBy("tjp.jabatan")
                                    ->groupBy("jumlah_orang.total_orang")
                                    ->groupBy("tmjp.threshold")
                                    ->get();
                if($get_data_jlh_penilai->count() > 0){
                    $get_report_penilaian=Trans_nilai_peserta_zonasi::from("trans_nilai_peserta_zonasi as tn")
                                            ->join("trans_peserta_zonasi as tpz", "tpz.id", "tn.id_peserta_zonasi")
                                            ->join("trans_pertanyaan_periode as tpp", "tpp.id", "tn.id_pertanyaan")
                                            ->join("variable_pertanyaan as vp", "vp.id", "tpp.id_variable")
                                            ->join("trans_observee as to", "to.IdObservee", "tpz.id_pegawai_penilai")
                                            ->join("tref_pegawai as tp", "tp.id_pegawai", "to.IdPegawai")
                                            ->select("tp.nama_pegawai", "to.NamaJabatan as jabatan", "to.bagian", "vp.variable", "tn.nilai")
                                            ->where("tpz.id_pegawai_peserta", $id_observee)
                                            ->where("tpp.id_periode", $id_periode)
                                            ->get();
                    if($get_report_penilaian->count() > 0){
                        $get_rata_rata=Trans_nilai_peserta_zonasi::from("trans_nilai_peserta_zonasi as tn")
                                            ->join("trans_peserta_zonasi as tpz", "tpz.id", "tn.id_peserta_zonasi")
                                            ->join("trans_pertanyaan_periode as tpp", "tpp.id", "tn.id_pertanyaan")
                                            ->join("variable_pertanyaan as vp", "vp.id", "tpp.id_variable")
                                            ->selectRaw("vp.variable, 
                                                AVG(tn.nilai) as rata_rata
                                            ")
                                            ->where("tpz.id_pegawai_peserta", $id_observee)
                                            ->where("tpp.id_periode", $id_periode)
                                            ->groupBy("vp.variable")
                                            ->get();
                        
                        
                        if($get_rata_rata->count() > 0){
                            $data_personal['nama']=$get_data_personal["nama_pegawai"];
                            $data_personal['foto']=$get_data_personal['foto_pegawai'];
                            $data_personal['nip']=$get_data_personal['nip'];
                            $data_personal['jabatan']=$get_data_personal['jabatan'];
                            $data_personal['bagian']=$get_data_personal['bagian'];
                            $data_personal['nilai_akhir']=$get_data_personal['nilai_akhir'];

                            foreach($get_data_jlh_penilai as $list_jlh_penilai){
                                $data_jlh_penilai[]=[
                                    "jabatan"=>$list_jlh_penilai['jabatan'],
                                    "jumlah_jabatan_penilai"=>$list_jlh_penilai['jumlah_jabatan_penilai'],
                                    "total_orang"=>$list_jlh_penilai['total_orang'],
                                    "keterangan"=>$list_jlh_penilai['threshold']."% dari ".$list_jlh_penilai['total_orang']." Orang"
                                ];
                            }

                            $nama_pegawai_before=null;
                            $x=0;
                            foreach($get_report_penilaian as $list_report){
                                if($nama_pegawai_before !== $list_report['nama_pegawai']){
                                    if(!is_null($nama_pegawai_before)){
                                        $x++;
                                    }
                                    $y=0;
                                    $data_report[$x]['nama_penilai']=$list_report['nama_pegawai'];
                                    $data_report[$x]['jabatan']=$list_report['jabatan'];
                                    $data_report[$x]['bagian']=$list_report['bagian'];
                                    $data_report[$x]['hasil']=[];
                                }
                                $data_report[$x]['hasil'][$y]['variable']=$list_report['variable'];
                                $data_report[$x]['hasil'][$y]['nilai']=$list_report['nilai'];
                                $y++;
                                $nama_pegawai_before=$list_report['nama_pegawai'];
                            }

                            foreach($get_rata_rata as $list_rata_rata){
                                $data_avg[]=[
                                    "variable"=>$list_rata_rata['variable'],
                                    "avg"=>$list_rata_rata['rata_rata']
                                ];
                            }
                            $status=true;
                            $msg="Data already reserved";
                        }else{
                            $msg="Nilai Rata - rata tidak ditemukan";
                        }
                    }else{
                        $msg="Data Laporan Penilaian tidak ditemukan";
                    }
                }else{
                    $msg="Data Jumlah Peserta Tidak ditemukan";
                }
                
            }else{
                $msg="Data Peserta tidak ditemukan";
            }

            return ['status'=>$status, 'msg'=>$msg, 'data_personal'=>$data_personal, "data_penilai"=>$data_jlh_penilai, 'data_report_penilaian'=>$data_report, 'data_avg'=>$data_avg];

        }
    }

?>