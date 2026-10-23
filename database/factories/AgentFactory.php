<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'team' => $this->faker->randomElement(['Team A', 'Team B', 'Team C']),
        ];
    }
}
