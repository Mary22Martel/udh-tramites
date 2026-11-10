<?php

namespace Database\Seeders;

/**
 * Facultades y programas académicos (carreras).
 * Archivo: database/data/carreras.csv
 * Ejecutar solo este seeder:  php artisan db:seed --class=CarrerasSeeder
 */
class CarrerasSeeder extends CsvSeeder
{
    public function run(): void
    {
        $this->importCsv('carreras', 'carreras.csv');
    }
}
