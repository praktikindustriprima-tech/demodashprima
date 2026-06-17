<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Onu extends Model
{
    protected $fillable = [
        'olt_id',
        'olt_index',
        'onu_index',
        'sn',
        'name',
        'model',
        'vlan',
        'status',
    ];

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }
}
