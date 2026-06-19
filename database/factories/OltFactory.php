<?php

namespace Database\Factories;

use App\Models\Olt;
use Illuminate\Database\Eloquent\Factories\Factory;

class OltFactory extends Factory
{
    protected $model = Olt::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'host' => fake()->ipv4(),
            'port' => 23,
            'username' => 'admin',
            'password' => fake()->password(),
            'olt_type' => 'ZTE C300',
        ];
    }
}
