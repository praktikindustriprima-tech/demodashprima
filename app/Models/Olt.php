<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Olt extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'olt_type',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Set the OLT password (encrypted).
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Get the decrypted OLT password.
     */
    public function getDecryptedPassword(): string
    {
        return Crypt::decryptString($this->attributes['password']);
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OltHistory::class);
    }
}
