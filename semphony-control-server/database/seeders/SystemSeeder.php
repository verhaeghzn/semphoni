<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Command;
use App\Models\System;
use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $system = System::query()->updateOrCreate(
            ['name' => 'TEST TESCAN System'],
            ['description' => 'Seeded test microscope system.'],
        );

        $client = Client::query()->updateOrCreate(
            ['api_key' => 'test-tescan-pc-api-key'],
            [
                'system_id' => $system->id,
                'name' => 'TEST TESCAN-PC Client',
                'width_px' => 1920,
                'height_px' => 1080,
                'can_screenshot' => true,
                'monitor_count' => 1,
            ],
        );

        $client->commands()->syncWithoutDetaching(
            Command::query()->pluck('id')->all(),
        );
    }
}
