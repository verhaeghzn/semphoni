<?php

namespace Database\Factories;

use App\Enums\ActionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Command>
 */
class CommandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'action_type' => ActionType::ButtonPress,
            'description' => fake()->boolean(50) ? fake()->sentence() : null,
        ];
    }
}
