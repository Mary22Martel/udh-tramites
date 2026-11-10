<?php

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;

/**
 * Ejemplos:
 *   php artisan udh:importar personal database/data/personal.csv
 *   php artisan udh:importar solicitudes_demo mis_cantidades.csv
 */
class ImportarCsv extends Command
{
    protected $signature = 'udh:importar {tipo : carreras | personal | estudiantes | tramites | solicitudes_demo} {archivo : ruta del CSV}';

    protected $description = 'Importa datos desde un archivo CSV (Excel) al sistema de trámites';

    public function handle(ImportService $import): int
    {
        $type = $this->argument('tipo');
        $file = $this->argument('archivo');

        if (! array_key_exists($type, ImportService::TYPES)) {
            $this->error('Tipo no válido. Usa: '.implode(', ', array_keys(ImportService::TYPES)));

            return self::FAILURE;
        }
        if (! is_file($file)) {
            $this->error("No se encontró el archivo: {$file}");

            return self::FAILURE;
        }

        $r = $import->import($type, $file);
        $this->info("Creados: {$r['created']} · Actualizados: {$r['updated']} · Omitidos: ".count($r['skipped']));
        foreach ($r['errors'] as $e) {
            $this->warn($e);
        }

        return self::SUCCESS;
    }
}
