<?php
    namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\HttpCache\Store;

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
                    SELECT  
                    tp.nama_pegawai, 
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
                    group by tp.nama_pegawai, toe2.NamaJabatan
                    order by nilai desc
                ";

                $data_report=Cache::store('redis')->remember("report_periode_zs_satker_{$id_periode}_{$id_zonasi_satker}_{$id_zonasi}", 3600*24*365, function() use($sql, $id_zonasi, $id_zonasi_satker){
                    return DB::select($sql, [$id_zonasi, $id_zonasi_satker]);
                });
                $status=true;
            }else{
                $msg="Data tidak konsisten";
            }


            return [
                'status'=>$status,
                'msg'=>$msg,
                'data'=>$data_report
            ];
        }
    }

?>