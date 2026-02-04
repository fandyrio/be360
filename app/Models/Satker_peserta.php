<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Satker_peserta extends Model
{
    protected $table="v_total_peserta_per_satker";
    protected $primaryKey="IdSatker";
    public $timestamps=false;
    public $incrementing=false;
}
