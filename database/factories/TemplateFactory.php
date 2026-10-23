<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => $this->faker->words(3, true) . ' template',
            'description'    => $this->faker->sentence(),
            'pass_threshold' => $this->faker->numberBetween(60, 90),
            'created_by'     => User::factory(),
        ];
    }
}
