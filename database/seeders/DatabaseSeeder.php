<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga TODA la información inicial:
 *   php artisan migrate:fresh --seed
 *
 * Cada parte lee un archivo de database/data/ que puedes editar en Excel.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CarrerasSeeder::class,          // facultades y carreras
            AreaSeeder::class,              // Tesorería, Matrícula (+ ventanillas), otras áreas
            PersonalSeeder::class,          // administrador, jefes, operadores, decanos, directores
            EstudiantesSeeder::class,       // estudiantes de demostración
            ProcedureSeeder::class,         // 72 trámites con requisitos y flujos
            TramitesPorCarreraSeeder::class, // trámites propios de cada carrera
            SolicitudesDemoSeeder::class,   // N solicitudes por carrera (estadísticas)
            DemoShowcaseSeeder::class,      // casos guionados para la presentación
            CajaSeeder::class,              // pagos hechos en Caja UDH (simulación)
        ]);
    }
}
