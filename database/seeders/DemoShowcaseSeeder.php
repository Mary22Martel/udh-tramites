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
        $pay = fn () => ['method' => 'BCP', 'operation' => (string) random_int(10000000, 99999999)];

        $start = function (string $email, string $code) use ($wf, $file, $pay) {
            $student = User::where('email', $email)->first();
            $proc = Procedure::with(['requirements', 'steps'])->where('name', $code)->first();
            if (! $student || ! $proc) {
                return null;
            }
            $files = $proc->requirements->mapWithKeys(fn ($r) => [$r->id => $file(str_replace(' ', '_', $r->name).'.pdf')])->all();

            return $wf->start($proc, $student, 'Solicito el trámite para continuar con mi proceso académico.', $files,
                $proc->requires_payment ? $pay() : null, $proc->requires_payment ? $file('Voucher_BCP.pdf') : null);
        };

        // Juan (Ing. Civil): una constancia esperando a Tesorería y un certificado observado
        $start('2021104829@udh.edu.pe', 'CONSTANCIA DE EGRESADO');
        if ($s = $start('2021104829@udh.edu.pe', 'CERTIFICADO DE ESTUDIOS')) {
            $wf->observe($s, $demo->actorFor($s), 'Voucher ilegible', 'Vuelva a adjuntar el comprobante de pago escaneado con buena resolución.');
        }

        // Ana (Derecho): un trámite finalizado con documento oficial
        if ($s = $start('2020201944@udh.edu.pe', 'CONSTANCIA DE EGRESADO')) {
            while ($s->status === 'EN_PROCESO') {
                $s = $wf->approve($s, $demo->actorFor($s));
            }
        }

        // Renzo (Ambiental) y Luciana (Contabilidad): trámites en Matrícula / Programa
        foreach (['2020210319@udh.edu.pe', '2021102941@udh.edu.pe'] as $email) {
            if ($s = $start($email, 'CERTIFICADO DE ESTUDIOS')) {
                $wf->approve($s, $demo->actorFor($s));
            }
        }

        $this->command?->info('  Casos de presentación creados');
    }
}
