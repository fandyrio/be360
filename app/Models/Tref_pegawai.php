<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_pegawai extends Model
{
    protected $table="tref_pegawai";
    protected $fillable=['id','id_pegawai', 'nama_pegawai', 'nip', 'status_pegawai'];
}
