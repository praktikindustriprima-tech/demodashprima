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
            'olt_index' => '1/1/'.fake()->numberBetween(1, 64),
            'onu_index' => fake()->numberBetween(1, 128),
            'sn' => 'ZTEG'.fake()->numerify('############'),
            'model' => fake()->randomElement(['ZTE-F670L', 'ZTE-F6600P', 'ZTE-F601']),
            'pw' => fake()->bothify('????####'),
            'scanned_at' => now(),
        ];
    }
}
