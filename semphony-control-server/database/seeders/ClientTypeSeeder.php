<?php

namespace Database\Seeders;

use App\Models\ClientType;
use App\Models\Command;
use Illuminate\Database\Seeder;

class ClientTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tescanSem = ClientType::query()->updateOrCreate(
            ['slug' => 'tescan_sem'],
            ['name' => 'TESCAN SEM'],
        );

        $tescanSemCommandNames = [
            'beam_on_off_toggle',
            'vacuum_stndby',
            'vacuum_vent',
            'vacuum_pump',
            'rbse_push_in',
            'rbse_pull_out',
            'detector_mix_a',
            'detector_mix_a_plus_b',
            'detector_mix_a_min_b',
            'detector_mix_a_and_b',
            'detector_mix_abcd',
            'trigger_degauss',
            'acquire',
            'continual_mode',
            'single_mode',
            'stage_control_stop',
            'get_metrics',
        ];

        $commandIdsByName = Command::query()
            ->whereIn('name', $tescanSemCommandNames)
            ->pluck('id', 'name');

        if ($commandIdsByName->count() !== count($tescanSemCommandNames)) {
            $missing = array_values(array_diff($tescanSemCommandNames, $commandIdsByName->keys()->all()));

            throw new \RuntimeException(
                'Missing commands for client type seed: '.implode(', ', $missing),
            );
        }

        $tescanSem->commands()->sync($commandIdsByName->values()->all());
    }
}
