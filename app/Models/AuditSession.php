<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'olt_id',
        'name',
        'status',
        'started_at',
        'completed_at',
        'onu_count',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function onus(): HasMany
    {
        return $this->hasMany(AuditSessionOnu::class);
    }

    public function savedOnus(): HasMany
    {
        return $this->hasMany(AuditSessionSavedOnu::class);
    }
}
