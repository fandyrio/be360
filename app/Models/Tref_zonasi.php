<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_zonasi extends Model
{
    public $timestamps=false;
    protected $primaryKey="IdZona";
    protected $table="tref_zonasi";
    protected $fillable=['IdZona', 'nama_zona', 'start_date', 'end_date', 'tahun_penilaian', 'is_active', 'proses_id', 'diinput_tgl', 'diinput_oleh', 'diperbarui_tgl', 'diperbarui_oleh'];
}
