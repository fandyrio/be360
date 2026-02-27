<?php
    namespace App\Services;
    use App\Models\Trans_nilai_peserta_zonasi;
    use App\Models\Trans_peserta_zonasi;
use App\Models\Tref_zonasi;

    class scriptService{
        protected $penilaianService;

        public function __construct(penilaianService $penilaian_service){
            $this->penilaianService=$penilaian_service;
        }

        public function runScriptIsiPertanyaan($id_zonasi){
            echo "==========================================\n";
            echo "Precondition Isi Jawaban\n";
            echo "==========================================\n";
            $get_zonasi=Tref_zonasi::where('id', $id_zonasi)->first();
            $id_periode=$get_zonasi['IdTahunPenilaian'];
            $get_pertanyaan=$this->penilaianService->getPertanyaanPeriode($id_periode, 'getAll');
            foreach($get_pertanyaan as $list_pertanyaan){
                $bobot["bobot_{$list_pertanyaan['id_pertanyaan_periode']}"]=$list_pertanyaan['bobot'];
            }
            echo "==========================================\n";
            echo "Running Script Isi Jawaban\n";
            echo "==========================================\n";
            $get_peserta_zonasi=Trans_peserta_zonasi::where('id_zonasi', $id_zonasi)->get();
            $jumlah_peserta=$get_peserta_zonasi->count();
            echo "Jumlah Peserta: ".$jumlah_peserta."\n";
            echo "==========================================\n";
            #1. Get all peserta
            echo "Mengisi jawaban peserta ...";
            $range=[1,2,3,4,5];

            foreach($get_peserta_zonasi as $list_peserta_zonasi){
                $get_nilai=Trans_nilai_peserta_zonasi::where('id_peserta_zonasi', $list_peserta_zonasi['id'])
                        ->where('nilai', 0)
                        ->where('locked', false);
                $nilai_peserta=clone $get_nilai->get();
                $current_nilai=0;
                foreach($nilai_peserta as $list_nilai){
                    $nilai=$range[array_rand($range)];
                    Trans_nilai_peserta_zonasi::where('id', $list_nilai['id'])
                                    ->update(['nilai'=>$nilai]);
                    $nilai_tmp=$nilai*$bobot["bobot_{$list_nilai['id_pertanyaan']}"] / 100;
                    $current_nilai += $nilai_tmp;
                }
                Trans_peserta_zonasi::where('id', $list_peserta_zonasi)->update(['nilai', $current_nilai]);
                clone $get_nilai->update(['locked'=>true, 'updated_at'=>date('Y-m-d H:i:s')]);
            }
            
        }

    }


?>