<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_pertanyaan_periode extends Model
{
    protected $table="trans_pertanyaan_periode";
    protected $fillable=['id', 'id_periode', 'id_pertanyaan'];
    
}
