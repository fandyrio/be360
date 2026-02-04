<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variable_pertanyaan extends Model
{
    protected $table="variable_pertanyaan";
    protected $fillable=['variable', 'kriteria', 'active'];
}
