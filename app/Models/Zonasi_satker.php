<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zonasi_satker extends Model
{
    protected $table="trans_zonasi_satker";
    protected $primaryKey="IdZonaSatker";
    public $timestamps=false;
    protected $fillable=['IdZonaSatker', 'IdZona', 'IdSatkerBanding', 'IdSatker', 'jumlah_personil', 'diinput_tgl', 'diinput_oleh', 'diperbaharui_tgl', 'diperbaharui_oleh'];
}
