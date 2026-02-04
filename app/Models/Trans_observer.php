<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_observer extends Model
{
    protected $table="trans_observer";
    protected $primaryKey="IdObserver";
    public $timestamps=false;
    protected $fillable=['IdObserver', 'idPegawai', 'NIPBaru', 'IdNamaJabatan', 'IdZonaSatker', 'IdObservee', 'diinput_tgl', 'diinput_oleh', 'start_penilaian', 'end_penilaian', 'end_penilaian_oleh'];
}
