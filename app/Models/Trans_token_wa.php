<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trans_token_wa extends Model
{
    protected $table="trans_token_wa";
    protected $fillable=['id_satker', 'category', 'payload', 'token', 'expired_at', 'status'];
}
