<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\DemoDataGenerator;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

/** Pantalla "Carga de datos": plantillas CSV, importación y datos de demostración. */
class ImportController extends Controller
{
    public function types()
    {
        return ['data' => collect(ImportService::TYPES)->map(fn ($t, $k) => ['key' => $k] + $t)->values()];
    }

    public function template(string $type, ImportService $import)
    {
        abort_unless(array_key_exists($type, ImportService::TYPES), 404);

        return response($import->template($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"plantilla_{$type}.csv\"",
        ]);
    }

    public function import(Request $request, ImportService $import)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ImportService::TYPES))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], ['file.mimes' => 'Sube un archivo .csv (en Excel: Archivo → Guardar como → CSV).']);

        @set_time_limit(300);

        return ['result' => $import->import($data['type'], $request->file('file')->getRealPath())];
    }

    public function generate(Request $request, DemoDataGenerator $demo)
    {
        $data = $request->validate([
            'program_ids' => ['array'],
            'program_ids.*' => ['exists:programs,id'],
            'count' => ['required', 'integer', 'min:1', 'max:200'],
        ]);
        @set_time_limit(300);
        $ids = empty($data['program_ids']) ? Program::pluck('id')->all() : $data['program_ids'];
        $total = $demo->generateBatch(array_fill_keys($ids, $data['count']), 60);

        return ['result' => ['created' => $total, 'updated' => 0, 'skipped' => [], 'errors' => []]];
    }

    /** Borra todo y vuelve a cargar los datos iniciales (database/data). */
    public function reset()
    {
        abort_unless(config('app.demo_mode'), 403, 'Solo disponible con DEMO_MODE=true.');
        @set_time_limit(600);
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

        return ['ok' => true];
    }
}
