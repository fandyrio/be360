<?php
    namespace App\Services;

use App\Models\Trans_observee;
use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_jabatan_peserta;
use App\Models\Tref_mapping_jabatan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                                ->select("trans_observee.IdObservee", 'c.nama_pegawai', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan', DB::raw('COUNT(b.id_pegawai_penilai) as jumlah'))
                                ->where('d.IdZona', $id_zonasi)
                                ->groupBy('c.nama_pegawai', 'trans_observee.IdObservee', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan')
                                ->havingRaw('COUNT(b.id_pegawai_penilai) <= 2')
                                ->get();
            foreach($get_data as $data_not_found){
                $data[]=[
                    'target' => Hashids::encode($data_not_found['IdObservee']),
                    'nama_pegawai'=> $data_not_found['nama_pegawai'],
                    'total'=>$data_not_found['total'],
                    'nama_satker'=>$data_not_found['NamaSatker'],
                    'endpoint'=>$data_not_found['endpoint'],
                    'jabatan'=>$data_not_found['NamaJabatan'],
                    'id_kelompok_jabatan'=>$data_not_found['id_kelompok_jabatan']
                ];
            }
            return ['data'=>$data, 'jumlah'=>count($data)];
        }

        public function getDoubleInsert($id_zonasi){
            // select
            // b.id, b.id_pegawai_penilai, c.nama_pegawai, count(id_pegawai_peserta), a.NamaJabatan, e.NamaSatker, a.endpoint
            // from trans_observee a
            // JOIN trans_peserta_zonasi b on b.id_pegawai_penilai = a.IdObservee
            // JOIN tref_pegawai c on c.id_pegawai = a.IdPegawai
            // JOIN trans_zonasi_satker d on d.IdZonaSatker = a.IdZonaSatker
            // JOIN v_satker e on e.IdSatker = d.IdSatker
            // where b.id_zonasi = 399 and b.id_jabatan_plt is null
            // group by id_pegawai_peserta, id_pegawai_penilai
            // Having count(id_pegawai_peserta) > 1
            $data = [];
            $get_data = Trans_observee::join("trans_peserta_zonasi as b", "b.id_pegawai_penilai", "trans_observee.IdObservee")
                                        ->join("tref_pegawai as c", "c.id_pegawai", "trans_observee.IdPegawai")
                                        ->join("trans_zonasi_satker as d", "d.IdZonaSatker", "trans_observee.IdZonaSatker")
                                        ->join("v_satker as e", "e.IdSatker", "d.IdSatker")
                                        ->select("b.id", "b.id_pegawai_penilai", "c.nama_pegawai", DB::raw('COUNT(id_pegawai_peserta)'), 'trans_observee.NamaJabatan', 'e.NamaSatker', 'trans_observee.endpoint')
                                        ->where('b.id_zonasi', $id_zonasi)
                                        ->whereNull('b.id_jabatan_plt')
                                        ->groupBy('b.id', 'b.id_pegawai_penilai', 'c.nama_pegawai', 'trans_observee.NamaJabatan', 'e.NamaSatker', 'trans_observee.endpoint')
                                        ->havingRaw('COUNT(id_pegawai_peserta) > 1')
                                        ->get();
            foreach($get_data as $list_double){
                $data[]=[
                    'target'=>Hashids::encode($list_double['id']),
                    'nama_pegawai'=>$list_double['nama_pegawai'],
                    'jumlah_insert'=>$list_double['jumlah'],
                    'jabatan'=>$list_double['NamaJabatan'],
                    'nama_satker'=>$list_double['NamaSatker'],
                    'endpoint'=>$list_double['endpoint']
                ];
            }

            return ['data'=>$data, 'jumlah'=>count($data)];
            
        }

        public function fix404($id_observee){
            $status = false;
            $data_insert = [];
            $get_data_penilai = Trans_peserta_zonasi::where('id_pegawai_penilai', $id_observee)->get();
            $jumlah_penilaian = $get_data_penilai->count();
            $existed_peserta = [];
            foreach($get_data_penilai as $list_penilai_existed){
                $existed_peserta[]=$list_penilai_existed['id_pegawai_peserta'];
            }

            $get_data_peserta = Trans_peserta_zonasi::where('id_pegawai_peserta', $id_observee)->get();
            $jumlah_kepesertaan = $get_data_peserta->count();
            $existed_penilai = [];
            foreach($get_data_peserta as $list_peserta_existed){
                $existed_penilai[]=$list_peserta_existed['id_pegawai_penilai'];
            }

            if($jumlah_penilaian <= 2){
                $get_observee = Trans_observee::join('trans_zonasi_satker as b', 'b.IdZonaSatker', 'trans_observee.IdZonaSatker')
                                    ->select("trans_observee.*", "b.IdZona", "b.IdSatker")
                                    ->where('IdObservee', $id_observee)->first();
                $id_satker = $get_observee->IdSatker;
                $get_jabatan = Tref_jabatan_peserta::where('id_kelompok_jabatan', $get_observee['id_kelompok_jabatan'])->first();
                $id_jabatan = $get_jabatan['id'];
                if(!is_null($get_jabatan['id_jabatan_gabungan'])){
                    $id_jabatan = $get_jabatan['id_jabatan_gabungan'];
                }
                
                $get_mapping = Tref_mapping_jabatan::where('id_jabatan_penilai', $id_jabatan)
                                                    ->where('active', true)
                                                    ->get();
                $data = [];
                $data_insert = [];
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
                    
                    

                    if($get_data_observee->count() === 1){
                        $batas = 1;
                    }else{
                        $batas = ceil($threshold * $get_data_observee->count() / 100);
                    }
                    $x=1;
                    foreach($get_data_observee as $list_data_insert){
                        if((int)$list_data_insert['IdObservee'] !== (int)$id_observee && $x <= $batas && !in_array((int)$list_data_insert['IdObservee'], $existed_peserta) ){
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
                        $x++;
                    }
                    
                }

                //untuk menilai ybs
                $get_mapping_penilai = Tref_mapping_jabatan::where('id_jabatan_peserta', $id_jabatan)
                                                        ->where('active', true)->get();

                foreach($get_mapping_penilai as $list_penilai){
                    $id_jabatan_penilai = $list_penilai['id_jabatan_penilai'];
                    $threshold_penilai = $list_penilai['threshold'];
                    
                    //check jabatan penilai apakah gabungan
                    $id_kelompok_jabatan_penilai = [];
                    $get_jabatan_penilai = Tref_jabatan_peserta::where('id', $id_jabatan_penilai)->first();
                    if((int)$get_jabatan_penilai->id_kelompok_jabatan === 0){
                        $id_jabatan_gabungan = $id_jabatan_penilai;
                        $get_jabatan_gabungan = Tref_jabatan_peserta::where('id_jabatan_gabungan', $id_jabatan_gabungan)->get();
                        foreach($get_jabatan_gabungan as $list_jabatan_gabungan){
                            $id_kelompok_jabatan_penilai[]=$list_jabatan_gabungan['id_kelompok_jabatan'];
                        }
                    }else{
                        $id_kelompok_jabatan_penilai[]=$get_jabatan_penilai->id_kelompok_jabatan;
                    }

                    $get_data_observee_penilai = Trans_observee::join('tref_pegawai as c', 'c.id_pegawai', 'trans_observee.IdPegawai')
                                ->join('trans_zonasi_satker as d',  'd.IdZonaSatker', 'trans_observee.IdZonaSatker')
                                ->join('v_satker as e', 'e.IdSatker', 'd.IdSatker')
                                ->select("trans_observee.IdObservee", 'c.nama_pegawai', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan', 'trans_observee.IdZonaSatker')
                                ->where('d.IdZona', $get_observee['IdZona'])
                                ->whereIn('id_kelompok_jabatan', $id_kelompok_jabatan_penilai)
                                ->where("d.IdSatker", $id_satker)
                                ->groupBy('c.nama_pegawai', 'trans_observee.IdObservee', 'e.NamaSatker', 'trans_observee.endpoint', 'trans_observee.NamaJabatan', 'trans_observee.id_kelompok_jabatan', 'trans_observee.IdZonaSatker')
                                ->get();
                    $jumlah_penilai = $get_data_observee_penilai->count();
                    if($jumlah_penilai > 0){
                        if($get_data_observee_penilai->count() === 1){
                            $batas = 1;
                        }else{
                            $batas = ceil($threshold_penilai * $get_data_observee_penilai->count() / 100);
                        }
                        $x=1;
                        foreach($get_data_observee_penilai as $list_data_insert_penilai){
                            if((int)$list_data_insert_penilai['IdObservee'] !== (int)$id_observee && $x <= $batas && !in_array($list_data_insert_penilai['IdObservee'], $existed_penilai)){
                                $data_insert[]=[
                                    'id_zonasi'=>$get_observee->IdZona,
                                    'id_zona_satker'=>$list_data_insert_penilai['IdZonaSatker'],
                                    'id_pegawai_peserta'=>$id_observee,
                                    'id_pegawai_penilai'=>$list_data_insert_penilai['IdObservee'],
                                    'id_jabatan_plt'=>null,
                                    'index_plt'=>0,
                                    'created_at'=>date("Y-m-d H:i:s")
                                ];
                            }
                            $x++;
                        }
                    }else{
                        $get_jabatan = Tref_jabatan_peserta::where('id_kelompok_jabatan', $id_kelompok_jabatan_penilai[0])->first();
                        $get_plt = Trans_peserta_zonasi::join('trans_zonasi_satker as b', 'b.IdZonaSatker', 'trans_peserta_zonasi.id_zona_satker')
                                                    ->select("trans_peserta_zonasi.*")
                                                    ->where('trans_peserta_zonasi.id_jabatan_plt', $get_jabatan->id)
                                                    ->where('trans_peserta_zonasi.id_zonasi', $get_observee['IdZona'])
                                                    ->where('b.IdSatker', $id_satker)
                                                    ->first();
                        // Log::warning("isi: ".$get_observee['IdZona']." : ".$id_satker, $id_kelompok_jabatan_penilai);
                        if(!is_null($get_plt)){
                            $data_insert[]=[
                                'id_zonasi'=>$get_observee->IdZona,
                                'id_zona_satker'=>$get_plt['id_zona_satker'],
                                'id_pegawai_peserta'=>$id_observee,
                                'id_pegawai_penilai'=>$get_plt['id_pegawai_penilai'],
                                'id_jabatan_plt'=>$get_jabatan->id,
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