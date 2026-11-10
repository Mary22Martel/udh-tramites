<?php

namespace Database\Seeders;

/**
 * Solicitudes de demostración: cuántas generar por carrera.
 * Archivo: database/data/solicitudes_demo.csv
 * Ejecutar solo este seeder:  php artisan db:seed --class=SolicitudesDemoSeeder
 */
class SolicitudesDemoSeeder extends CsvSeeder
{
    public function run(): void
    {
        $this->importCsv('solicitudes_demo', 'solicitudes_demo.csv');
    }
}
