<?php

namespace Database\Factories;

use App\Models\AuditSession;
use App\Models\AuditSessionOnu;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditSessionOnuFactory extends Factory
{
    protected $model = AuditSessionOnu::class;

    public function definition(): array
    {
        return [
            'audit_session_id' => AuditSession::factory(),
            'olt_index' => 'gpon-onu_1/1/'.fake()->numberBetween(1, 64).':'.fake()->numberBetween(1, 128),
            'onu_index' => fake()->numberBetween(1, 128),
            'sn' => 'ZTEG'.fake()->numerify('############'),
            'state' => fake()->randomElement(['unknown', 'online', 'offline']),
            'scanned_at' => now(),
        ];
    }
}
