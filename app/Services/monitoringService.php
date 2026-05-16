<?php
    namespace App\Services;

use App\Models\Trans_observee;
use Illuminate\Support\Facades\DB;

    class monitoringService{
        public function getDoubleInsertPeserta($id_zonasi){
            // select
            // a.IdObservee,c.nama_pegawai, count(b.id_pegawai_penilai) as jumlah, e.NamaSatker, a.endpoint, a.NamaJabatan, a.id_kelompok_jabatan
            // from trans_observee a
            // LEFT JOIN trans_peserta_zonasi b on b.id_pegawai_penilai = a.IdObservee
            // JOIN tref_pegawai c on c.id_pegawai = a.IdPegawai
            // JOIN trans_zonasi_satker d on d.IdZonaSatker = a.IdZonaSatker
            // JOIN v_satker e on e.IdSatker = d.IdSatker
            // where d.IdZona = 399
            // group by c.nama_pegawai
            // HAVING count(b.id_pegawai_penilai) = 0
            // order by a.NamaJabatan, e.NamaSatker
            $data = [];
            $get_data = Trans_observee::leftJoin('trans_peserta_zonasi as b', 'b.id_pegawai_penilai', 'trans_observee.IdObservee')
                                ->join('tref_pegawai as c', 'c.id_pegawai', 'a.id_pegawai')
                                ->join('trans_zonasi_satker d on d.IdZonaSatker', 'a.IdZonaSatker')
                                ->join('v_satker as e', 'e.IdSatker', 'd.IdSatker')
                                ->select("a.IdObservee", 'c.nama_pegawai', DB::raw('count(b.id_pegawai_penilai) as total'), 'e.NamaSatker', 'a.endpoint', 'a.NamaJabatan', 'a,id_kelompok_jabatan')
                                ->where('d.IdZona', $id_zonasi)
                                ->having('total', '=', 0)
                                ->get();
            foreach($get_data as $data_double){
                $data[]=[
                    'id_observee' => $data_double['IdObservee'],
                    'nama_pegawai'=> $data_double['nama_pegawai'],
                    'total'=>$data_double['total'],
                    'nama_satker'=>$data_double['nama_satker'],
                    'endpoint'=>$data_double['endpoint'],
                    'jabatan'=>$data_double['jabatan'],
                    'id_kelompok_jabatan'=>$data_double['id_kelompok_jabatan']
                ];
            }
            return ['data'=>$data, 'jumlah'=>count($data)];
        }
    }



?>