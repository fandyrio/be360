<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tref_sys_config extends Model
{
    protected $table="tref_sys_config";
    protected $fillable=['id', 'config_name', 'config_value_str', 'config_value_int'];
}
