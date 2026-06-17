<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OltHistory extends Model
{
    protected $table = 'olt_history';

    protected $fillable = [
        'user_id',
        'olt_id',
        'action',
        'target_sn',
        'command_sent',
        'response_raw',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }
}
