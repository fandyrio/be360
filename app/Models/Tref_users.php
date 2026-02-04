<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Tref_users extends Authenticatable implements JWTSubject
{
    public $timestamps=false;
    protected $table="tref_users";
    protected $primaryKey="IdUser";
    protected $fillable=["IdUser", 'uname', "IdPegawai", "NamaLengkap", "NIPBaru", "IdRole", "passwd", "email", "passTemp", "passwdTemp_activation", "last_reset", "IdNamaJabatan", "IdSatker", "diinput_tgl", "diinput_oleh", "diperbarui_tgl", "diperbarui_oleh", "is_active"];

    public function getJWTIdentifier(){
        return $this->getKey();
    }

    public function getJWTCustomClaims(){
        return [];
    }
}
