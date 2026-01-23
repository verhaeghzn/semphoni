<?php

namespace Database\Factories;

use App\Enums\LogDirection;
use App\Models\Client;
use App\Models\Command;
use App\Models\System;
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
        $system = System::factory();

        return [
            'system_id' => $system,
            'client_id' => Client::factory()->for($system),
            'direction' => fake()->randomElement([LogDirection::Inbound, LogDirection::Outbound]),
            'command_id' => fake()->boolean(70) ? Command::factory() : null,
            'summary' => fake()->sentence(),
            'payload' => fake()->boolean(50) ? ['example' => fake()->word()] : null,
        ];
    }
}
