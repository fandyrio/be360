<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Majelis_hakim extends Model
{
    protected $table = "tref_majelis_hakim";
    protected $fillable = ["id", "nama_majelis", "IdObservee", "id_periode", "id_zoansi_satker", "status"];
}
