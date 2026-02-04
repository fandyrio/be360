<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_jabatan_kosong extends Model
{
    protected $table="trans_jabatan_kosong";
    protected $fillable=['id', 'id_zonasi', 'id_zonasi_satker', 'status'];
}
