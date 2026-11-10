<?php

namespace Database\Seeders;

/**
 * Reporte de trámites por carrera exportado del sistema académico (columnas destiptra; desesc).
 * Vincula cada trámite con todas las carreras donde aparece y crea los que falten.
 * Archivo: database/data/reporte_tramites.csv
 * Ejecutar solo este seeder:  php artisan db:seed --class=TramitesPorCarreraSeeder
 */
class TramitesPorCarreraSeeder extends CsvSeeder
{
    public function run(): void
    {
        $this->importCsv('tramites', 'reporte_tramites.csv');
    }
}
