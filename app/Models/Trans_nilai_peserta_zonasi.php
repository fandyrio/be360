<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_nilai_peserta_zonasi extends Model
{
    protected $table="trans_nilai_peserta_zonasi";
    
    public function transZonasiPeserta(){
        return $this->belongsTo(Trans_peserta_zonasi::class, 'id_peserta_zonasi', 'id');
    }

    public function pertanyaan(){
        return $this->belongsTo(Tref_pertanyaan::class, 'id_pertanyaan', 'id');
    }

    public function tref_jawaban_bundle(){
        return $this->hasMany(Tref_jawaban_bundle::class, 'bundle_code', 'bundle_code_jawaban');
    }
}
