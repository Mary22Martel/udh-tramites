<?php

namespace Database\Seeders;

/**
 * Estudiantes.
 * Archivo: database/data/estudiantes.csv
 * Ejecutar solo este seeder:  php artisan db:seed --class=EstudiantesSeeder
 */
class EstudiantesSeeder extends CsvSeeder
{
    public function run(): void
    {
        $this->importCsv('estudiantes', 'estudiantes.csv');
    }
}
