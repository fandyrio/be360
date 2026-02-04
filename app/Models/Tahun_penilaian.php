<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tahun_penilaian extends Model
{
    public $timestamps=false;
    protected $table="tref_tahun_penilaian";
    protected $primaryKey="IdTahunPenilaian";
    protected $fillable=['IdTahunPenilaian', 'tahun', 'dasar_hukum', 'keterangan'];

}
