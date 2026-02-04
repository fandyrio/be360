<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_observee extends Model
{
    protected $table='trans_observee';
    protected $primaryKey='IdObservee';
    public $timestamps=false;
    protected $fillable=['IdObservee', 'IdPegawai', 'NIPBaru', 'id_kelompok_jabatan', 'IdNamaJabatan', 'IdZonaSatker', 'diinput_tgl', 'diinput_oleh'];
}
