<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pertanyaan_category extends Model
{
    protected $table = "tref_pertanyaan_category";
    protected $fillable = ["id", "category_id", "id_pertanyaan", "bobot"];
}
