<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tahapan_proses extends Model
{
    protected $table="tref_tahapan_proses";
    protected $fillable=['id', 'proses'];
}
