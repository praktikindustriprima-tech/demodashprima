<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OltTemplate extends Model
{
    protected $fillable = ['name', 'host', 'port', 'username', 'password', 'is_default'];

    protected $hidden = [];
}
