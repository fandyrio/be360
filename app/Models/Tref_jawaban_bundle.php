<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_jawaban_bundle extends Model
{
    protected $table="tref_jawaban_bundle";
    protected $fillable=["bundle_code", "bundle_name", "jawaban_text", "point_jawaban", "active"];

    
}
