<?php

namespace Database\Factories;

use App\Models\AuditSession;
use App\Models\Olt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditSessionFactory extends Factory
{
    protected $model = AuditSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'olt_id' => Olt::factory(),
            'name' => 'AUDIT-'.now()->format('Ymd').'-'.fake()->numerify('###'),
            'status' => 'active',
            'started_at' => now(),
            'onu_count' => 0,
        ];
    }
}
