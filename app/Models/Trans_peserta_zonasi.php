<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_peserta_zonasi extends Model
{
    protected $table="trans_peserta_zonasi";

    public function nilaiZonasi(){
        $this->hasMany(Trans_nilai_peserta_zonasi::class, 'id_peserta_zonasi', 'id');
    }
}
