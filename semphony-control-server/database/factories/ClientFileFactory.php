<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientFile>
 */
class ClientFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_filename' => fake()->word().'.'.fake()->fileExtension(),
            'storage_type' => 'sfs',
            'storage_configuration_id' => null,
            'storage_path' => 'client-uploads/1/'.fake()->word().'.'.fake()->fileExtension(),
            'mime' => fake()->mimeType(),
            'bytes' => fake()->numberBetween(1000, 1000000),
            'sha256' => str_repeat('a', 64),
            'uploaded_at' => now(),
        ];
    }
}
