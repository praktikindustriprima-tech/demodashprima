<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class OltTemplate extends Model
{
    protected $fillable = ['name', 'host', 'port', 'username', 'password', 'is_default'];

    protected $hidden = [];
}
