<?php

namespace Database\Seeders;

use App\Models\CajaPago;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Pagos de demostración en la Caja UDH (matrícula, pensiones, carné...).
 * Simula lo que devolvería el sistema de caja real de la universidad.
 */
class CajaSeeder extends Seeder
{
    protected const CONCEPTS = [
        ['Matrícula semestre 2026-I', 250.00],
        ['Pensión de enseñanza 2026-I · cuota 1', 480.00],
        ['Pensión de enseñanza 2026-I · cuota 2', 480.00],
        ['Matrícula semestre 2026-II', 250.00],
        ['Pensión de enseñanza 2026-II · cuota 1', 480.00],
        ['Carné universitario 2026', 20.00],
        ['Derecho de biblioteca', 15.00],
        ['Seguro estudiantil 2026', 35.00],
    ];

    public function run(): void
    {
        $students = User::where('role', 'ESTUDIANTE')->whereNotNull('student_code')->get();
        $cashiers = ['Caja 1 · Sede central', 'Caja 2 · Sede central', 'Caja 3 · La Esperanza'];
        $n = 0;

        foreach ($students as $st) {
            $pick = collect(self::CONCEPTS)->random(random_int(3, 6))->sortBy(fn ($c) => array_search($c, self::CONCEPTS));
            foreach ($pick as [$concept, $amount]) {
                $semesterII = str_contains($concept, '2026-II');
                $date = Carbon::now()->subDays($semesterII ? random_int(60, 110) : random_int(150, 300))->setTime(random_int(8, 17), random_int(0, 59));
                CajaPago::create([
                    'receipt_number' => CajaPago::nextReceipt(),
                    'student_code' => $st->student_code,
                    'student_name' => $st->name,
                    'concept' => $concept,
                    'amount' => $amount,
                    'cashier' => $cashiers[array_rand($cashiers)],
                    'paid_at' => $date,
                ]);
                $n++;
            }
        }

        $this->command?->info("  Caja UDH → {$n} pagos de demostración");
    }
}
