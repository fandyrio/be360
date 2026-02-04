<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_pertanyaan extends Model
{
    protected $table="tref_pertanyaan";
    protected $fillable=['id', 'id_variable', 'pertanyaan', 'bundle_code_pertanyaan', 'bobot', 'active'];


}
