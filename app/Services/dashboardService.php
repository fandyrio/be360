<?php
    namespace App\Services;

use App\Models\Tahun_penilaian;
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
    }




?>