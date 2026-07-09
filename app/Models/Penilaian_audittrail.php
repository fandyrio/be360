<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penilaian_audittrail extends Model
{
    protected $table="penilaian_audittrail";
    protected $fillable = ["id", "id_peserta_zonasi", "id_pegawai_penilai", "id_pegawai_dinilai", "jumlah_penilai", "bobot_jabatan_penilai", "nilai_akhir", "nilai"];
}
