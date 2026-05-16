<?php
    namespace App\Services;

use App\Models\Trans_observee;
use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_jabatan_peserta;
use App\Models\Tref_mapping_jabatan;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

    class monitoringService{
        public function getLinkNotFound($id_zonasi){
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
                                ->join('tref_pegawai as c', 'c.id_pegawai', 'trans_observee.IdPegawai')
                                ->join('trans_zonasi_satker as d',  'd.IdZonaSatker', 'trans_observee.IdZonaSatker')
                                ->join('v_satker as e', 'e.IdSatker', 'd.IdSatker')
                                ->select("trans_observee.IdObservee", 'c.nama_pegawai', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan', )
                                ->where('d.IdZona', $id_zonasi)
                                ->whereNull('b.id_pegawai_penilai')
                                ->groupBy('c.nama_pegawai', 'trans_observee.IdObservee', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan')
                                ->get();
            foreach($get_data as $data_double){
                $data[]=[
                    'id_observee' => Hashids::encode($data_double['IdObservee']),
                    'nama_pegawai'=> $data_double['nama_pegawai'],
                    'total'=>$data_double['total'],
                    'nama_satker'=>$data_double['NamaSatker'],
                    'endpoint'=>$data_double['endpoint'],
                    'jabatan'=>$data_double['NamaJabatan'],
                    'id_kelompok_jabatan'=>$data_double['id_kelompok_jabatan']
                ];
            }
            return ['data'=>$data, 'jumlah'=>count($data)];
        }

        public function fix404($id_observee){
            $status = false;
            $data_insert = [];
            $get_data = Trans_peserta_zonasi::where('id_pegawai_penilai', $id_observee)->exists();
            if(!$get_data){
                $get_observee = Trans_observee::join('trans_zonasi_satker as b', 'b.IdZonaSatker', 'trans_observee.IdZonaSatker')
                                    ->select("trans_observee.*", "b.IdZona", "b.IdSatker")
                                    ->where('IdObservee', $id_observee)->first();
                $id_satker = $get_observee->IdSatker;
                $get_jabatan = Tref_jabatan_peserta::where('id_kelompok_jabatan', $get_observee['id_kelompok_jabatan'])->first();
                $id_jabatan = $get_jabatan['id'];
                if(!is_null($get_jabatan['id_jabatan_gabungan'])){
                    $id_jabatan = $get_jabatan['id_jabatan_gabungan'];
                }
                
                $get_mapping = Tref_mapping_jabatan::where('id_jabatan_penilai', $id_jabatan)->get();
                $data = [];
                foreach($get_mapping as $list_mapping){
                    $id_jabatan_peserta = $list_mapping['id_jabatan_peserta'];
                    $threshold = $list_mapping['threshold'];
                    
                    //check jabatan peserta apakah gabungan
                    $id_kelompok_jabatan = [];
                    $get_jabatan_peserta = Tref_jabatan_peserta::where('id', $id_jabatan_peserta)->first();
                    if((int)$get_jabatan_peserta->id_kelompok_jabatan === 0){
                        $id_jabatan_gabungan = $id_jabatan_peserta;
                        $get_jabatan_gabungan = Tref_jabatan_peserta::where('id_jabatan_gabungan', $id_jabatan_gabungan)->get();
                        foreach($get_jabatan_gabungan as $list_jabatan_gabungan){
                            $id_kelompok_jabatan[]=$list_jabatan_gabungan['id_kelompok_jabatan'];
                        }
                    }else{
                        $id_kelompok_jabatan[]=$get_jabatan_peserta->id_kelompok_jabatan;
                    }

                    $get_data_observee = Trans_observee::leftJoin('trans_peserta_zonasi as b', 'b.id_pegawai_penilai', 'trans_observee.IdObservee')
                                ->join('tref_pegawai as c', 'c.id_pegawai', 'trans_observee.IdPegawai')
                                ->join('trans_zonasi_satker as d',  'd.IdZonaSatker', 'trans_observee.IdZonaSatker')
                                ->join('v_satker as e', 'e.IdSatker', 'd.IdSatker')
                                ->select("trans_observee.IdObservee", 'c.nama_pegawai', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan', 'trans_observee.IdZonaSatker')
                                ->where('d.IdZona', $get_observee['IdZona'])
                                ->whereIn('id_kelompok_jabatan', $id_kelompok_jabatan)
                                ->where("d.IdSatker", $id_satker)
                                ->groupBy('c.nama_pegawai', 'trans_observee.IdObservee', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan', 'trans_observee.IdZonaSatker')
                                ->get();

                    
                    $batas = $threshold * $get_data_observee->count() / 100;
                    $data_insert = [];
                    foreach($get_data_observee as $list_data_insert){
                        if((int)$list_data_insert['IdObservee'] !== (int)$list_mapping['IdObservee']){
                            $data_insert[]=[
                                'id_zonasi'=>$get_observee->IdZona,
                                'id_zona_satker'=>$list_data_insert['IdZonaSatker'],
                                'id_pegawai_peserta'=>$list_data_insert['IdObservee'],
                                'id_pegawai_penilai'=>$id_observee,
                                'id_jabatan_plt'=>null,
                                'index_plt'=>0,
                                'created_at'=>date("Y-m-d H:i:s")
                            ];
                        }
                    }
                    
                }
                DB::table("trans_peserta_zonasi")->insert($data_insert);
                $status=true;
                $msg = "Berhasil generate data";
            }else{
                $msg = "Tidak ada masalah";
            }
            return ['status'=>$status, 'msg'=>$msg];
        }
    }



?>