<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_jabatan_peserta extends Model
{
    protected $table="tref_jabatan_peserta";
    protected $fillable=['id_kelompok_jabatan', 'jabatan', 'active'];
}
