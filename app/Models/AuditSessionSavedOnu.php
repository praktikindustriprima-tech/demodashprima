<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditSessionSavedOnu extends Model
{
    use HasFactory;

    protected $table = 'audit_session_saved_onus';

    protected $fillable = [
        'audit_session_id',
        'olt_index',
        'sn',
        'model',
        'pw',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AuditSession::class);
    }
}
