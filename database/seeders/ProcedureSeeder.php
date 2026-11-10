<?php

namespace Database\Seeders;

use App\Models\Procedure;
use App\Models\Program;
use App\Services\ProcedureBuilder;
use Illuminate\Database\Seeder;

/**
 * Catálogo de trámites (TUPA) con requisitos y flujo de pasos.
 * Archivo: database/data/procedures.json (149 trámites del reporte, clasificados en 7 categorías).
 */
class ProcedureSeeder extends Seeder
{
    public function run(): void
    {
        $builder = app(ProcedureBuilder::class);
        $items = json_decode(file_get_contents(database_path('data/procedures.json')), true);
        $programs = Program::pluck('id', 'code');

        foreach ($items as $p) {
            $p['program_ids'] = collect($p['programs'] ?? [])->map(fn ($c) => $programs[$c] ?? null)->filter()->values()->all();
            $builder->save($p, Procedure::where('code', $p['code'])->first());
        }

        $this->command?->info('  procedures.json → '.count($items).' trámites');
    }
}
