<?php

namespace Database\Factories;

use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_id' => System::factory(),
            'name' => fake()->words(asText: true),
            'api_key' => Str::random(40),
            'width_px' => fake()->numberBetween(800, 4000),
            'height_px' => fake()->numberBetween(600, 3000),
            'can_screenshot' => fake()->boolean(),
            'monitor_count' => null,
            'is_active' => true,
        ];
    }
}
