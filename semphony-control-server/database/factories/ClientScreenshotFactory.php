<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientScreenshot>
 */
class ClientScreenshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_nr' => 1,
            'mime' => 'image/jpeg',
            'storage_disk' => 'local',
            'storage_path' => 'client-screenshots/1/monitor-1/latest.jpg',
            'bytes' => 1234,
            'sha256' => str_repeat('a', 64),
            'taken_at' => now(),
        ];
    }
}
