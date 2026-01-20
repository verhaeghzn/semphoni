<?php

namespace Database\Seeders;

use App\Enums\ActionType;
use App\Models\Command;
use Illuminate\Database\Seeder;

class CommandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $commands = [
            'beam_on_off_toggle' => "Electron Beam section, button labeled 'BEAM ON'.",
            'vacuum_stndby' => null,
            'vacuum_vent' => "Vacuum panel bottom row, grey 'VENT' button.",
            'vacuum_pump' => "Vacuum panel bottom row, blue 'PUMP' button.",
            'rbse_push_in' => "Motorized RBSE panel, left button 'Push In'.",
            'rbse_pull_out' => "Motorized RBSE panel, middle button 'Pull Out'.",
            'detector_mix_a' => "SEM Detectors & Mixer row, radio 'A'.",
            'detector_mix_a_plus_b' => "SEM Detectors & Mixer row, radio 'A+B'.",
            'detector_mix_a_min_b' => "SEM Detectors & Mixer row, radio 'A-B'.",
            'detector_mix_a_and_b' => "SEM Detectors & Mixer row, radio 'A|B'.",
            'detector_mix_abcd' => "SEM Detectors & Mixer row, radio 'AB|CD'.",
            'trigger_degauss' => 'Top-right main toolbar; degauss icon location is approximate.',
            'acquire' => "Info Panel tabs row, 'Acquire'.",
            'continual_mode' => "Info Panel tabs row, 'Continual'.",
            'single_mode' => "Info Panel tabs row, 'Single'.",
            'stage_control_stop' => "Stage Control panel bottom-left, 'Stop' button.",
        ];

        foreach ($commands as $name => $description) {
            Command::query()->updateOrCreate(
                ['name' => $name],
                [
                    'action_type' => ActionType::ButtonPress,
                    'description' => $description,
                ],
            );
        }

        Command::query()->updateOrCreate(
            ['name' => 'heartbeat'],
            [
                'action_type' => ActionType::Heartbeat,
                'description' => 'Keepalive / presence heartbeat.',
            ],
        );

        Command::query()->updateOrCreate(
            ['name' => 'get_screenshot'],
            [
                'action_type' => ActionType::ButtonPress,
                'description' => 'Fetch a screenshot from the client.',
            ],
        );
    }
}
