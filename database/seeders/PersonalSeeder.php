<?php

namespace Database\Seeders;

/**
 * Personal administrativo, decanos y directores.
 * Archivo: database/data/personal.csv
 * Ejecutar solo este seeder:  php artisan db:seed --class=PersonalSeeder
 */
class PersonalSeeder extends CsvSeeder
{
    public function run(): void
    {
        $this->importCsv('personal', 'personal.csv');
    }
}
