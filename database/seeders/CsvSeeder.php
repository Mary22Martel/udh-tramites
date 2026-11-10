<?php

namespace Database\Seeders;

use App\Services\ImportService;
use Illuminate\Database\Seeder;

/**
 * Base para seeders que leen un CSV de la carpeta database/data.
 * Los CSV se pueden abrir y editar con Excel (separador ; ).
 */
abstract class CsvSeeder extends Seeder
{
    protected function importCsv(string $type, string $file): void
    {
        $path = database_path('data/'.$file);
        if (! file_exists($path)) {
            $this->command?->warn("No existe {$path}, se omite.");

            return;
        }

        $r = app(ImportService::class)->import($type, $path);
        $this->command?->info(sprintf('  %s → %d creados, %d actualizados, %d omitidos', $file, $r['created'], $r['updated'], count($r['skipped'])));
        foreach ($r['errors'] as $e) {
            $this->command?->warn('    '.$e);
        }
    }
}
