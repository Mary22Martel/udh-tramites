<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Áreas centrales (Tesorería, Matrícula, ...) y sus ventanillas por carrera.
 * Archivo: database/data/areas.json
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = json_decode(file_get_contents(database_path('data/areas.json')), true);

        foreach ($areas as $a) {
            $area = Area::updateOrCreate(['code' => $a['code']], [
                'name' => $a['name'],
                'description' => $a['description'] ?? null,
                'is_system' => $a['is_system'] ?? false,
                'has_channels' => $a['has_channels'] ?? false,
            ]);

            foreach ($a['channels'] ?? [] as $c) {
                $channel = $area->channels()->firstOrCreate(['name' => $c['name']]);
                $channel->programs()->sync(Program::whereIn('code', $c['programs'])->pluck('id'));
            }
        }

        $this->command?->info('  areas.json → '.count($areas).' áreas');
    }
}
