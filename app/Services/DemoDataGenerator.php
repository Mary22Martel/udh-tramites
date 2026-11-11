<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Program;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera solicitudes de demostración realistas recorriendo el flujo real
 * (iniciar → aprobar pasos → observar / subsanar / rechazar / finalizar),
 * con fechas distribuidas en los últimos meses para que los reportes tengan datos.
 */
class DemoDataGenerator
{
    protected const NOMBRES = ['Luis', 'María', 'José', 'Rosa', 'Carlos', 'Ana', 'Jorge', 'Lucía', 'Miguel', 'Carmen', 'Pedro', 'Sofía', 'Diego', 'Valeria', 'Renzo', 'Fiorella', 'Kevin', 'Milagros', 'Junior', 'Heydy', 'Kathleen', 'Brayan', 'Yessenia', 'Frank', 'Lizbeth', 'Edwin', 'Gianella', 'Alexis', 'Rocío', 'Jhon'];

    protected const APELLIDOS = ['Huamán', 'Quispe', 'Ramírez', 'Espinoza', 'Torres', 'Flores', 'Cárdenas', 'Rojas', 'Mendoza', 'Salazar', 'Ponce', 'Albornoz', 'Tucto', 'Ccori', 'Malpartida', 'Berrospi', 'Trujillo', 'Sánchez', 'Martel', 'Araujo', 'Casas', 'Gabriel', 'Ayala', 'Valdivia', 'Durand', 'Robles', 'Céspedes', 'Inga', 'Leandro', 'Soto'];

    protected const OBSERVATIONS = [
        ['Voucher ilegible', 'Vuelva a adjuntar el comprobante de pago escaneado con buena resolución.'],
        ['Fotografía no cumple el formato', 'La foto debe ser a color, fondo blanco y vestimenta formal.'],
        ['Documento incompleto', 'Falta la firma del asesor en el documento adjunto.'],
        ['Monto de pago incorrecto', 'El monto abonado no coincide con el TUPA vigente. Adjunte el pago complementario.'],
    ];

    protected const REJECTIONS = [
        'El estudiante mantiene deuda pendiente con la universidad',
        'No cumple con los créditos mínimos exigidos por el reglamento',
        'Solicitud presentada fuera del plazo establecido en el calendario académico',
    ];

    protected const REASONS = [
        'Solicito el presente trámite para continuar con mi proceso académico en la universidad.',
        'Requiero el documento para presentarlo en mi centro de prácticas pre profesionales.',
        'Necesito el trámite para postular a una beca de estudios.',
        'Solicito atención del trámite para cumplir con los requisitos de mi proceso de titulación.',
        'Requiero el documento para fines laborales en una institución pública.',
    ];

    public function __construct(protected WorkflowService $wf) {}

    /** Crea estudiantes de demostración para una carrera (si aún no tiene suficientes). */
    public function ensureStudents(Program $program, int $min = 6): \Illuminate\Support\Collection
    {
        $students = User::where('role', 'ESTUDIANTE')->where('program_id', $program->id)->get();
        $semesters = ['IV Ciclo', 'V Ciclo', 'VI Ciclo', 'VII Ciclo', 'VIII Ciclo', 'IX Ciclo', 'X Ciclo'];

        while ($students->count() < $min) {
            $year = random_int(2019, 2024);
            do {
                $code = $year.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            } while (User::where('student_code', $code)->exists());
            $name = self::NOMBRES[array_rand(self::NOMBRES)].' '.self::APELLIDOS[array_rand(self::APELLIDOS)].' '.self::APELLIDOS[array_rand(self::APELLIDOS)];
            $students->push(User::create([
                'name' => $name,
                'email' => "{$code}@udh.edu.pe",
                'password' => ImportService::defaultPasswordHash(),
                'role' => 'ESTUDIANTE',
                'student_code' => $code,
                'program_id' => $program->id,
                'faculty_id' => $program->faculty_id,
                'current_semester' => $semesters[array_rand($semesters)],
                'academic_state' => random_int(1, 10) > 8 ? 'EGRESADO' : 'REGULAR',
                'dni' => (string) random_int(70000000, 79999999),
            ]));
        }

        return $students;
    }

    /** Genera $count solicitudes para una carrera. Devuelve cuántas creó. */
    public function generateForProgram(Program $program, int $count, int $daysBack = 180): int
    {
        return $this->generateBatch([$program->id => $count], $daysBack);
    }

    /**
     * Genera solicitudes para varias carreras a la vez, en orden cronológico
     * (así los códigos UDH-AAAA-NNNNN quedan ordenados por fecha).
     *
     * @param  array<int,int>  $counts  [program_id => cantidad]
     */
    public function generateBatch(array $counts, int $daysBack = 180): int
    {
        $realNow = Carbon::now();
        $plan = [];
        foreach ($counts as $programId => $n) {
            $program = Program::find($programId);
            if (! $program || $n <= 0) {
                continue;
            }
            $students = $this->ensureStudents($program, max(4, (int) ceil($n / 4)));
            for ($i = 0; $i < $n; $i++) {
                // Más solicitudes en meses recientes
                $days = (int) floor(($daysBack + 1) * (1 - sqrt(mt_rand() / mt_getrandmax())));
                $plan[] = [
                    'student' => $students->random(),
                    'at' => $realNow->copy()->subDays($days)->setTime(random_int(8, 17), random_int(0, 59)),
                ];
            }
        }
        usort($plan, fn ($a, $b) => $a['at'] <=> $b['at']);

        $created = 0;
        foreach ($plan as $item) {
            if ($item['at']->gte($realNow)) {
                $item['at'] = $realNow->copy()->subMinutes(random_int(30, 240));
            }
            $procedures = $this->wf->availableProceduresFor($item['student']);
            if ($procedures->isEmpty()) {
                continue;
            }
            // Los trámites propios de la carrera aparecen con más frecuencia
            $own = $procedures->where('target_scope', 'PROGRAMA');
            $procedure = ($own->isNotEmpty() && random_int(1, 10) <= 4 ? $own : $procedures)->random();

            DB::transaction(fn () => $this->simulate($procedure->fresh(['steps', 'requirements']), $item['student'], $item['at'], $realNow));
            $created++;
        }

        Carbon::setTestNow();

        return $created;
    }

    protected function simulate($procedure, User $student, Carbon $clock, Carbon $realNow): Submission
    {
        Carbon::setTestNow($clock);

        $files = [];
        foreach ($procedure->requirements as $req) {
            if ($req->is_required || random_int(0, 1)) {
                $ext = in_array('.pdf', $req->file_types ?? [], true) ? 'pdf' : 'jpg';
                $files[$req->id] = ['name' => str_replace(' ', '_', mb_substr($req->name, 0, 40)).'_'.$student->student_code.'.'.$ext, 'size' => random_int(120, 2400) * 1024];
            }
        }
        $s = $this->wf->start($procedure, $student, self::REASONS[array_rand(self::REASONS)], $files, enforceLimits: false);

        // Trámite con costo: el estudiante paga su ticket (algunos recientes quedan sin pagar)
        if ($s->status === 'PENDIENTE_PAGO') {
            $recent = $clock->diffInDays($realNow) < 10;
            if (($recent && random_int(1, 100) <= 30) || ! $this->tick($realNow, 5, 60 * 20)) {
                Carbon::setTestNow();

                return $s;
            }
            $s = $this->payTicket($s);
        }

        // Destino de la simulación
        $roll = random_int(1, 100);
        $outcome = match (true) {
            $roll <= 48 => 'FINALIZADO',
            $roll <= 76 => 'EN_PROCESO',
            $roll <= 90 => 'OBSERVADO',
            default => 'RECHAZADO',
        };
        // Las solicitudes antiguas casi siempre ya terminaron (como en la realidad)
        $ageDays = $clock->diffInDays($realNow);
        if ($ageDays > 21 && in_array($outcome, ['EN_PROCESO', 'OBSERVADO'], true) && random_int(1, 100) <= 85) {
            $outcome = 'FINALIZADO';
        }
        $steps = $procedure->steps->count();
        $stopAt = $outcome === 'FINALIZADO' ? $steps : random_int(1, max(1, $steps - 1));
        $alreadyObserved = false;

        while ($s->status === 'EN_PROCESO' && $s->current_step_index < $stopAt) {
            if (! $this->tick($realNow)) {
                break; // no inventamos eventos en el futuro
            }
            $actor = $this->actorFor($s);

            // Algunas solicitudes se observan y luego se subsanan en el camino
            if (! $alreadyObserved && $outcome !== 'OBSERVADO' && random_int(1, 100) <= 12 && $this->wf->currentStep($s)?->can_observe) {
                [$reason, $detail] = self::OBSERVATIONS[array_rand(self::OBSERVATIONS)];
                $s = $this->wf->observe($s, $actor, $reason, $detail);
                $alreadyObserved = true;
                if (! $this->tick($realNow)) {
                    break; // queda observada
                }
                $s = $this->wf->correct($s, $student, 'Adjunto el documento corregido según lo indicado.', ['name' => 'Subsanacion_'.$s->code.'.pdf', 'size' => 320 * 1024]);
                $alreadyObserved = true;

                continue;
            }

            $s = $this->approveDemo($s, $actor);
        }

        if ($s->status === 'EN_PROCESO' && in_array($outcome, ['OBSERVADO', 'RECHAZADO'], true) && $this->tick($realNow)) {
            $step = $this->wf->currentStep($s->load('procedure.steps'));
            $actor = $this->actorFor($s);
            if ($outcome === 'OBSERVADO' && $step?->can_observe) {
                [$reason, $detail] = self::OBSERVATIONS[array_rand(self::OBSERVATIONS)];
                $s = $this->wf->observe($s, $actor, $reason, $detail);
            } elseif ($outcome === 'RECHAZADO' && $step?->can_reject) {
                $s = $this->wf->reject($s, $actor, self::REJECTIONS[array_rand(self::REJECTIONS)]);
            }
        }

        // Fecha de última actualización coherente con la simulación
        $s->timestamps = false;
        $s->updated_at = Carbon::now();
        $s->save();
        Carbon::setTestNow();

        return $s;
    }

    /** Aprueba el paso actual como lo haría el área (en el último paso sube el documento final). */
    public function approveDemo(Submission $s, ?User $actor = null): Submission
    {
        $s->loadMissing('procedure.steps');
        $actor ??= $this->actorFor($s);
        $isLast = $s->current_step_index >= $s->procedure->steps->count() - 1;
        $slug = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($s->procedure_name, 40, ''), '_');
        $file = $isLast
            ? ['name' => "{$slug}_{$s->code}.pdf", 'size' => random_int(180, 900) * 1024]
            : (random_int(1, 100) <= 25 ? ['name' => "informe_{$s->code}.pdf", 'size' => random_int(120, 600) * 1024] : null);

        return $this->wf->approve($s, $actor, null, $file);
    }

    /** Paga el ticket pendiente de una solicitud con un medio de pago al azar (pasarela de prueba). */
    public function payTicket(Submission $s): Submission
    {
        $ticket = $s->payments()->where('status', 'PENDIENTE')->first();
        if (! $ticket) {
            return $s;
        }
        // Uno de cada cuatro paga en Caja UDH (presencial)
        if (random_int(1, 4) === 1 && $ticket->user?->student_code) {
            $caja = app(\App\Services\CajaService::class);
            $row = $caja->simulate($ticket);
            $row->update(['cashier' => ['Caja 1 · Sede central', 'Caja 2 · Sede central', 'Caja 3 · La Esperanza'][random_int(0, 2)]]);
            $caja->sync($ticket->user);

            return $s->fresh();
        }
        $method = ['YAPE', 'YAPE', 'YAPE', 'PLIN', 'TARJETA', 'TARJETA'][random_int(0, 5)];
        $reference = $method === 'TARJETA' ? '•••• '.random_int(1000, 9999) : '9•• ••• '.random_int(100, 999);

        return $this->wf->markPaid($ticket, $method, $reference, 'TX-'.now()->format('ymd').'-'.strtoupper(\Illuminate\Support\Str::random(6)), 'SIMULADO');
    }

    /** Avanza el reloj simulado. Devuelve false si llegaría al futuro. */
    protected function tick(Carbon $realNow, int $minMinutes = 90, int $maxMinutes = 60 * 40): bool
    {
        $next = Carbon::now()->copy()->addMinutes(random_int($minMinutes, $maxMinutes));
        if ($next->hour < 8 || $next->hour > 18) {
            $next->addHours(14);
        }
        if ($next->gte($realNow)) {
            return false;
        }
        Carbon::setTestNow($next);

        return true;
    }

    /** Busca al responsable real que debe atender el paso actual (para que el historial tenga nombres reales). */
    public function actorFor(Submission $s): User
    {
        $s->loadMissing('procedure.steps');
        $area = $s->current_area;

        $candidate = match ($area) {
            'PROGRAMA_ACADEMICO' => User::where('role', 'PROGRAMA_ACADEMICO')->where('program_id', $s->program_id)->first(),
            'FACULTAD' => User::where('role', 'FACULTAD')->where('faculty_id', $s->faculty_id)->first(),
            default => null,
        };

        if (! $candidate && $area && ! in_array($area, WorkflowService::STUDENT_AREAS, true)) {
            $areaModel = Area::with('channels')->where('code', $area)->first();
            $channel = $s->channel_id ? $areaModel?->channels->firstWhere('id', $s->channel_id) : null;
            $candidate = ($channel?->responsible_id ? User::find($channel->responsible_id) : null)
                ?? ($areaModel?->responsible_id ? User::find($areaModel->responsible_id) : null)
                ?? User::where('area_id', $areaModel?->id)->first();
        }

        if ($candidate && $this->wf->canAct($candidate, $s)) {
            return $candidate;
        }

        return User::where('role', 'ADMINISTRADOR')->firstOrFail();
    }
}
