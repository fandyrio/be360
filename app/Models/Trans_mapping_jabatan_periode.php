<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_mapping_jabatan_periode extends Model
{
    protected $table="trans_mapping_jabatan_periode";
    protected $fillable=['id', 'id_periode', 'id_mapping_jabatan'];
}
