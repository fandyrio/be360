<?php
    namespace App\Services;

use App\Models\Tahun_penilaian;
use App\Models\Trans_jabatan_kosong;
use App\Models\Zonasi_satker;
use Illuminate\Support\Facades\Cache;

    class dashboardService{
        public function getAllPeriode(){
            $get_periode=Tahun_penilaian::all();
            return $get_periode;
        }

        public function runningPeriode(){
            $get_periode=Tahun_penilaian::where("proses_id", 1)->first();
            return $get_periode;
        }
        public function lastPeriode(){
            $get_data=Tahun_penilaian::orderBy("IdTahunPenilaian", "desc")->first();
            return $get_data;
        }
        
        public function getRataRataAll($refresh = null){
            $get_last_periode=$this->lastPeriode();
            $id_last_periode = $get_last_periode->IdTahunPenilaian;
            if(!is_null($refresh)){
                Cache::store("redis")->forget("nilai_rata2_last_periode");
            }
            $get_rata_rata=Cache::store("redis")->remember("nilai_rata2_last_periode", 3600*24*365, function() use($id_last_periode){
                return Tahun_penilaian::from("trans_nilai_peserta_zonasi as tn")
                                            ->join("trans_peserta_zonasi as tpz", "tpz.id", "tn.id_peserta_zonasi")
                                            ->join("trans_pertanyaan_periode as tpp", "tpp.id", "tn.id_pertanyaan")
                                            ->join("variable_pertanyaan as vp", "vp.id", "tpp.id_variable")
                                            ->selectRaw("vp.variable, 
                                                AVG(tn.nilai) as rata_rata
                                            ")
                                            ->where("tpp.id_periode", $id_last_periode)
                                            ->groupBy("vp.variable")
                                            ->get();
            });
            return $get_rata_rata;
        }

        public function getDataDashboardSatker($satker_id){
            $ada_jabatan_kosong=false;
            $blm_kirim_penilaian=false;
            $get_jabatan_kosong=Trans_jabatan_kosong::join("trans_zonasi_satker as tzs", "tzs.IdZonaSatker", "trans_jabatan_kosong.id_zonasi_satker")
                                    ->where("tzs.IdSatker", $satker_id)
                                    ->where("trans_jabatan_kosong.status", false)
                                    ->exists();
            if($get_jabatan_kosong){
                $ada_jabatan_kosong=true;
            }

            $get_penilaian=Zonasi_satker::where("IdSatker", $satker_id)->get();
            
            $jlh_penilaian=$get_penilaian->count();
            
            if($jlh_penilaian > 0){
                foreach($get_penilaian as $list_penilaian_satker){
                    if($list_penilaian_satker['kirim_penilaian'] === 0){
                        $blm_kirim_penilaian=true;
                    }
                }
            }
            return ['blm_kirim_penilaian'=>$blm_kirim_penilaian, 'ada_jabatan_kosong'=>$ada_jabatan_kosong, 'jlh_penilaian'=>$jlh_penilaian];
        }
    }




?>