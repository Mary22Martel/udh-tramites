<?php

namespace Database\Seeders;

use App\Models\Procedure;
use App\Models\User;
use App\Services\DemoDataGenerator;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

/**
 * Casos "guionados" para la presentación: cada estudiante demo
 * tiene solicitudes en estados interesantes para mostrar el flujo.
 */
class DemoShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $wf = app(WorkflowService::class);
        $demo = app(DemoDataGenerator::class);
        $file = fn ($n) => ['name' => $n, 'size' => 420 * 1024];

        $start = function (string $email, string $name, bool $pay = true) use ($wf, $demo, $file) {
            $student = User::where('email', $email)->first();
            $proc = Procedure::with(['requirements', 'steps'])->where('name', $name)->first();
            if (! $student || ! $proc) {
                return null;
            }
            $files = $proc->requirements->where('is_required', true)->mapWithKeys(fn ($r) => [$r->id => $file(str_replace(' ', '_', $r->name).'.pdf')])->all();
            $s = $wf->start($proc, $student, 'Solicito el trámite para continuar con mi proceso académico.', $files);

            return $pay ? $demo->payTicket($s) : $s;
        };

        // Juan (Ing. Civil): un ticket por pagar (para mostrar Culqi en la demo) y un trámite observado
        $start('2021104829@udh.edu.pe', 'CONSTANCIA DE EGRESADO', pay: false);
        if ($s = $start('2021104829@udh.edu.pe', 'CERTIFICADO DE ESTUDIOS')) {
            $wf->observe($s, $demo->actorFor($s), 'Fotografía no cumple el formato', 'La foto debe ser a color, fondo blanco y vestimenta formal. Adjunta una nueva.');
        }

        // Ana (Derecho): un trámite finalizado con documento oficial
        if ($s = $start('2020201944@udh.edu.pe', 'CONSTANCIA DE EGRESADO')) {
            while ($s->status === 'EN_PROCESO') {
                $s = $demo->approveDemo($s);
            }
        }

        // Renzo (Ambiental) y Luciana (Contabilidad): trámites pagados y en curso
        foreach (['2020210319@udh.edu.pe', '2021102941@udh.edu.pe'] as $email) {
            if ($s = $start($email, 'CERTIFICADO DE ESTUDIOS')) {
                $demo->approveDemo($s);
            }
        }

        $this->command?->info('  Casos de presentación creados');
    }
}
