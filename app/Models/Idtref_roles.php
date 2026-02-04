<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Idtref_roles extends Model
{
    public $timestamps=false;
    public $primaryKey="IdRole";
    protected $table="tref_roles";
    protected $filleable=["IdRole", "code", "rolename", "is_active"];
}
