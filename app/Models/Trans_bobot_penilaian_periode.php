<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_bobot_penilaian_periode extends Model
{
    protected $table="trans_bobot_penilaian_periode";
    protected $fillable=['id', 'id_periode', 'id_bobot_penilaian'];
}
