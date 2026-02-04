<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log_msg extends Model
{
    protected $table="log_msg";
    protected $fillable=['id', 'data_id', 'category', 'msg', 'status'];
}
