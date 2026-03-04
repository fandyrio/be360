<?php
    namespace App\Services;

use App\Models\Trans_bobot_penilaian_periode;
use App\Models\Trans_nilai_peserta_zonasi;
use App\Models\Trans_observee;
use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_jabatan_peserta;
use App\Models\Tref_zonasi;
use Exception;
use Illuminate\Support\Facades\DB;

    class scriptService{
        protected $penilaianService;

        public function __construct(penilaianService $penilaian_service){
            $this->penilaianService=$penilaian_service;
        }

        public function runScriptIsiPertanyaan($id_zonasi){
            echo "==========================================\n";
            echo "Precondition Isi Jawaban\n";
            echo "==========================================\n";
            $get_zonasi=Tref_zonasi::where('IdZona', $id_zonasi)->first();
            $id_periode=$get_zonasi['IdTahunPenilaian'];
            $get_pertanyaan=$this->penilaianService->getPertanyaanPeriode($id_periode, 'getAll');
            foreach($get_pertanyaan as $list_pertanyaan){
                $bobot["bobot_{$list_pertanyaan['id_pertanyaan_periode']}"]=$list_pertanyaan['bobot'];
            }

            #get Bobot penilaian masing - masing jabatan
            $get_bobot_penilaian_jabatan=Trans_bobot_penilaian_periode::join("tref_bobot_penilaian as tbp", function($join) use($id_periode){
                                            $join->on("tbp.id", "=", "trans_bobot_penilaian_periode.id_bobot_penilaian")
                                            ->where("id_periode", $id_periode);
                                        })
                                        ->select("trans_bobot_penilaian_periode.bobot", "tbp.id_jabatan_peserta", "tbp.id_jabatan_penilai")
                                        ->get();
            $bobot_penilaian_jabatan=null;
            foreach($get_bobot_penilaian_jabatan as $list_bobot_jabatan){
                $bobot_penilaian_jabatan["bobot_{$list_bobot_jabatan['id_jabatan_penilai']}_{$list_bobot_jabatan['id_jabatan_peserta']}"]=$list_bobot_jabatan['bobot'];
            }
            echo "==========================================\n";
            echo "Running Script Isi Jawaban\n";
            echo "==========================================\n";
            $get_peserta_zonasi=Trans_peserta_zonasi::join("trans_observee as to1", "to1.IdObservee", "=", "trans_peserta_zonasi.id_pegawai_peserta")
                                                    ->join("tref_jabatan_peserta as tjp", "tjp.id_kelompok_jabatan", '=', 'to1.id_kelompok_jabatan')
                                                    ->join("trans_observee as to2", "to2.IdObservee", "=", "trans_peserta_zonasi.id_pegawai_penilai")
                                                    ->join("tref_jabatan_peserta as tjp2", "tjp2.id_kelompok_jabatan", "=", "to2.id_kelompok_jabatan")
                                                    ->select("trans_peserta_zonasi.id_zona_satker,
                                                    trans_peserta_zonasi.id_jabatan_plt", 
                                                    "trans_peserta_zonasi.id_pegawai_peserta", 
                                                    "trans_peserta_zonasi.id_pegawai_penilai", 
                                                    "trans_peserta_zonasi.nilai", 
                                                    "tjp.id_jabatan_gabungan as id_jabatan_gabungan_peserta", 
                                                    "tjp.id_kelompok_jabatan as id_kelompok_jabatan_peserta", 
                                                    "tjp2.id_jabatan_gabungan as id_jabatan_gabungan_penilai", 
                                                    "tjp2.id_kelompok_jabatan as id_kelompok_jabatan_penilai", 
                                                    "tjp.id as id_jabatan_peserta", 
                                                    "tjp2.id as id_jabatan_penilai")
                                                ->where('trans_peserta_zonasi.id_zonasi', $id_zonasi)->get();
            $jumlah_peserta=$get_peserta_zonasi->count();
            echo "Jumlah Penilaian: ".$jumlah_peserta."\n";
            echo "==========================================\n";
            #1. Get all peserta
            echo "Mengisi jawaban peserta ...";
            $range=[1,2,3,4,5];


            $data_insert=[];
            foreach($get_peserta_zonasi as $list_peserta_zonasi){
                $is_plt=false;
                $id_pegawai_penilai=$list_peserta_zonasi['id_pegawai_penilai'];
                $id_pegawai_peserta=$list_peserta_zonasi['id_pegawai_peserta'];
                $id_jabatan_penilai=$list_peserta_zonasi['id_jabatan_penilai'];
                $id_jabatan_peserta=$list_peserta_zonasi['id_jabatan_peserta'];
                $get_nilai=Trans_nilai_peserta_zonasi::where('id_peserta_zonasi', $list_peserta_zonasi['id'])
                        ->where('nilai', 0)
                        ->where('locked', false);
                $nilai_peserta=clone $get_nilai->get();
                $current_nilai=0;
                if($nilai_peserta->count() === 0){
                    #2. Kalau belum ada pertanyaan, generate pertanyaan
                    $id_reference=NULL;
                    if(!is_null($list_peserta_zonasi['id_jabatan_plt'])){
                        $check_pz_plt=Trans_peserta_zonasi::where("id_pegawai_peserta", $list_peserta_zonasi['id_pegawai_peserta'])
                                        ->where("id_pegawai_penilai", $list_peserta_zonasi['id_pegawai_penilai'])
                                        ->whereRaw("id_jabatan_plt is not null")
                                        ->first();
                        $id_reference=$check_pz_plt['id'];
                        $id_kelompok_jabatan_penilai=$list_peserta_zonasi['id_kelompok_jabatan_penilai'];
                    }else{
                        $is_plt=true;
                        $id_kelompok_jabatan_penilai=$list_peserta_zonasi['id_jabatan_plt'];
                    }
                    
                    if(!is_null($list_peserta_zonasi['id_jabatan_gabungan_peserta'])){
                        $id_jabatan_peserta=$list_peserta_zonasi['id_jabatan_gabungan_peserta'];
                    }

                    if(!is_null($list_peserta_zonasi['id_jabatan_gabungan_penilai'])){
                        $id_jabatan_penilai=$list_peserta_zonasi['id_jabatan_gabungan_penilai'];
                    }

                    try{
                        $nilai_peserta=0;
                        foreach($get_pertanyaan as $list_pertanyaan){
                            $nilai=$range[array_rand($range)];
                            $data_insert[]=[
                                "id_peserta_zonasi"=>$list_peserta_zonasi['id'],
                                "id_pertanyaan"=>$list_pertanyaan['id'],
                                "id_reference"=>$id_reference,
                                "nilai"=>$nilai,
                                "locked"=>1,
                                "updated_at"=>date("Y-m-d H:i:s")
                            ];
                            #3. Convert ke nilai Bobot Perentase masing - masing pertanyaan 
                            $nilai_bobot=$list_pertanyaan['bobot'] * $nilai / 100; 
                            $nilai_peserta+=$nilai_bobot;
                        }

                        
                        #hitung orang yang ada di jabatan itu
                        $get_observee=Trans_observee::where("id_kelompok_jabatan", $id_kelompok_jabatan_penilai)
                                            ->where("IdZonaSatker", $list_peserta_zonasi['id_zona_satker'])
                                            ->get();
                        $id_observee=[];                    
                        foreach($get_observee as $list_observee){
                            $id_observee[]=$list_observee['id'];
                        }
                        $jlh_penilaian=Trans_peserta_zonasi::whereIn("id_pegawai_penilai", $id_observee)
                                                        ->where("id_pegawai_peserta", $list_peserta_zonasi['id_pegawai_peserta'])
                                                        ->count();
                        echo "\rId Penilaian: ";
                        for($x=0;$x<count($id_observee);$x++){
                            echo "Id pegawai peserta : ".$list_peserta_zonasi['id_pegawai_peserta']." - id pegawai penilai: ".$id_observee[$x]."\r";
                        }
                        if($is_plt === true){
                            $jlh_penilaian+=1;
                        }
                        // if($id_jabatan_penilai === 1 && $id_jabatan_peserta === 1){
                        //     $bobot_penilaian=100;
                        // }else{
                            $bobot_penilaian=$bobot_penilaian_jabatan["bobot_{$id_jabatan_penilai}_{$id_jabatan_peserta}"];
                            echo $id_jabatan_penilai." : ".$id_jabatan_peserta." = ".$bobot_penilaian."<br />";
                        // }
                        $nilai_total=((($nilai_peserta * $bobot_penilaian) / 100) / $jlh_penilaian);
                        $get_current_nilai=Trans_observee::where("id", $id_pegawai_peserta)->first();
                        $current_total=$get_current_nilai['total_nilai']+=$nilai_total;
                        try{
                            DB::beginTransaction();
                                Trans_peserta_zonasi::table('trans_nilai_peserta_zonasi')->insert($data_insert);
                                $get_current_nilai->total_nilai=$current_total;
                                $get_current_nilai->update();
                            DB::commit();
                        }catch(\Exception $e){
                            DB::rollBack();
                            $msg=$e->getMessage();
                        }
                    }catch(\Exception $e){
                        echo "Error: ".$e->getMessage()." ".$e->getLine();
                    }
                    #3. Simpan Nilai masing - masing
                }
                
            }
            
        }

    }


?>