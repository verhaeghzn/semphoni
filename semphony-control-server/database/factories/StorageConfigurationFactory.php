<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StorageConfiguration>
 */
class StorageConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['sftp', 's3']),
            'configuration' => match (fake()->randomElement(['sftp', 's3'])) {
                'sftp' => [
                    'host' => fake()->domainName(),
                    'username' => fake()->userName(),
                    'password' => fake()->password(),
                    'port' => 22,
                    'root' => '/',
                ],
                's3' => [
                    'key' => fake()->uuid(),
                    'secret' => fake()->uuid(),
                    'region' => fake()->randomElement(['us-east-1', 'us-west-2', 'eu-west-1']),
                    'bucket' => fake()->word(),
                ],
            },
            'is_active' => true,
        ];
    }
}
