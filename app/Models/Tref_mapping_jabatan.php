<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_mapping_jabatan extends Model
{
    protected $table="tref_mapping_jabatan";
    protected $fillable=['id_jabatan_peserta', 'id_jabatan_penilai', 'threshold', 'active'];
}
