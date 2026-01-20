<?php

namespace Database\Factories;

use App\Enums\LogDirection;
use App\Models\Client;
use App\Models\Command;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientLog>
 */
class ClientLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'direction' => fake()->randomElement([LogDirection::Inbound, LogDirection::Outbound]),
            'command_id' => fake()->boolean(70) ? Command::factory() : null,
            'summary' => fake()->sentence(),
            'payload' => fake()->boolean(50) ? ['example' => fake()->word()] : null,
        ];
    }
}
