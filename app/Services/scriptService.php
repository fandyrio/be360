<?php
    namespace App\Services;
    use App\Models\Trans_peserta_zonasi;

    class scriptService{

        public function runScriptIsiPertanyaan($id_zonasi){
            echo "==========================================\n";
            echo "Running Script Isi Jawaban\n";
            echo "==========================================\n";
            $get_zonasi=Trans_peserta_zonasi::where('id_zonasi', $id_zonasi)->get();
            $jumlah_peserta=$get_zonasi->count;
            echo "Jumlah Peserta: ".$jumlah_peserta."\n";
        }

    }


?>