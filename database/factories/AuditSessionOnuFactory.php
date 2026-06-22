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
            'olt_index' => 'gpon-olt_1/'.fake()->numberBetween(1, 16).'/'.fake()->numberBetween(1, 64),
            'onu_index' => fake()->numberBetween(1, 128),
            'model' => fake()->randomElement(['F670LV9.0', 'F660PV9.0', 'F680V9.0']),
            'sn' => 'ZTEG'.fake()->numerify('############'),
            'pw' => fake()->bothify('??######'),
            'scanned_at' => now(),
        ];
    }
}
