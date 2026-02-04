<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_bobot_penilaian extends Model
{
    protected $table="tref_bobot_penilaian";
    protected $fillable=['id', 'id_jabatan_peserta', 'id_jabatan_penilaian', 'bobot', 'active'];
}
