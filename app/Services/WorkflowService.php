<?php

namespace App\Services;

use App\Mail\DocumentoTramiteMail;
use App\Services\MailAccounts;
use App\Models\Area;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\ProcedureStep;
use App\Models\Submission;
use App\Models\SubmissionDocument;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Support\BusinessDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Motor de flujo de trámites.
 *
 * Cada trámite (Procedure) tiene una lista ordenada de pasos (ProcedureStep).
 * Una solicitud (Submission) avanza paso a paso: cada área aprueba, observa o rechaza.
 * El paso actual define qué área / ventanilla / programa / facultad ve la tarea en su bandeja.
 */
class WorkflowService
{
    /** Áreas "dinámicas" que dependen del estudiante y no de un área central. */
    public const STUDENT_AREAS = ['ESTUDIANTE', 'PROGRAMA_ACADEMICO', 'FACULTAD'];

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    public static function normalize(string $name): string
    {
        return trim(Str::lower(Str::ascii($name)));
    }

    /** Nombre legible del área responsable de un paso. */
    public function areaLabel(?string $area, ?Submission $s = null): string
    {
        return match ($area) {
            null, '' => 'Sistema',
            'ESTUDIANTE' => 'Estudiante',
            'PROGRAMA_ACADEMICO' => $s?->program ? 'Programa de '.$s->program->name : 'Programa académico',
            'FACULTAD' => $s?->faculty?->name ?? 'Facultad',
            'SISTEMA' => 'Sistema de Trámites UDH',
            default => $this->areas()->get($area)?->name ?? $area,
        };
    }

    protected ?Collection $areaCache = null;

    /** @return Collection<string, Area> áreas centrales indexadas por código */
    public function areas(): Collection
    {
        return $this->areaCache ??= Area::all()->keyBy('code');
    }

    public function currentStep(Submission $s): ?ProcedureStep
    {
        $steps = $s->procedure?->steps ?? collect();

        return $steps->values()->get($s->current_step_index);
    }

    /** Calcula a qué área y ventanilla le toca la solicitud según el paso actual. */
    public function resolveAssignment(Submission $s): void
    {
        $step = $this->currentStep($s);
        $s->current_area = $step?->area;
        $s->channel_id = null;

        if ($step && ! in_array($step->area, self::STUDENT_AREAS, true)) {
            $area = Area::with('channels.programs')->where('code', $step->area)->first();
            if ($area && $area->has_channels) {
                $channel = $area->channels->first(fn ($c) => $c->programs->contains('id', $s->program_id));
                $s->channel_id = $channel?->id;
            }
        }
    }

    /**
     * Arranca el plazo del área actual: días hábiles configurados en el paso (3 por defecto).
     * Se llama cada vez que la solicitud llega a un área (al pagar, al avanzar, al subsanar o al ser derivada).
     */
    public function startStepClock(Submission $s): void
    {
        $step = $this->currentStep($s);
        if (! $step || $step->area === 'ESTUDIANTE') {
            $s->step_started_at = null;
            $s->step_due_at = null;

            return;
        }
        $s->step_started_at = now();
        $s->step_due_at = BusinessDays::add(now(), max(1, (int) ($step->days ?: ProcedureStep::DEFAULT_DAYS)));
    }

    /** Área (código) que atiende un usuario del personal: PROGRAMA_ACADEMICO, FACULTAD, MATRICULA... */
    public function userArea(User $user): ?string
    {
        return match (true) {
            $user->role === 'PROGRAMA_ACADEMICO' => 'PROGRAMA_ACADEMICO',
            $user->role === 'FACULTAD' => 'FACULTAD',
            default => $user->area?->code,
        };
    }

    // ------------------------------------------------------------------
    // Catálogo
    // ------------------------------------------------------------------

    /**
     * Trámites disponibles para un estudiante con "precedencia":
     * si su carrera tiene una versión propia de un trámite, esa reemplaza a la general.
     */
    public function availableProceduresFor(User $student): Collection
    {
        $all = Procedure::with(['programs:id', 'faculties:id'])
            ->withCount('steps')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $programId = $student->program_id;
        $facultyId = $student->faculty_id;

        $programSpecific = $all->filter(fn ($p) => $p->target_scope === 'PROGRAMA' && $p->programs->contains('id', $programId));
        $covered = $programSpecific->map(fn ($p) => self::normalize($p->name))->flip();

        $facultySpecific = $all->filter(fn ($p) => $p->target_scope === 'FACULTAD'
            && $p->faculties->contains('id', $facultyId)
            && ! $covered->has(self::normalize($p->name)));
        foreach ($facultySpecific as $p) {
            $covered[self::normalize($p->name)] = true;
        }

        $general = $all->filter(fn ($p) => $p->target_scope === 'TODOS' && ! $covered->has(self::normalize($p->name)));

        return $programSpecific->concat($facultySpecific)->concat($general)->values();
    }

    // ------------------------------------------------------------------
    // Bandejas
    // ------------------------------------------------------------------

    /** Solicitudes pendientes que le corresponde atender a un usuario. */
    public function tasksQuery(User $user): Builder
    {
        $q = Submission::query()->where('status', 'EN_PROCESO');

        if ($user->isAdmin()) {
            return $q->where('current_area', '!=', 'ESTUDIANTE');
        }

        if ($user->role === 'PROGRAMA_ACADEMICO') {
            return $q->where('current_area', 'PROGRAMA_ACADEMICO')->where('program_id', $user->program_id);
        }

        if ($user->role === 'FACULTAD') {
            return $q->where('current_area', 'FACULTAD')->where('faculty_id', $user->faculty_id);
        }

        $area = $user->area_id ? Area::with('channels')->find($user->area_id) : null;
        if (! $area) {
            return $q->whereRaw('1 = 0');
        }

        $q->where('current_area', $area->code);

        if ($area->has_channels) {
            $myChannels = $area->channels->where('responsible_id', $user->id)->pluck('id');
            if ($myChannels->isNotEmpty()) {
                // Operador de ventanilla: solo ve las carreras de su ventanilla
                $q->whereIn('channel_id', $myChannels);
            }
            // Jefe del área (u otro miembro sin ventanilla): supervisa todas
        }

        return $q;
    }

    /** Personal de Tesorería: ve todos los pagos y el detalle de sus solicitudes. */
    public static function isTreasury(User $user): bool
    {
        return $user->role === 'TESORERIA' || $user->area?->code === 'TESORERIA';
    }

    public function canAct(User $user, Submission $s): bool
    {
        return $s->status === 'EN_PROCESO' && $this->tasksQuery($user)->whereKey($s->id)->exists();
    }

    public function canView(User $user, Submission $s): bool
    {
        if ($user->isAdmin() || $s->student_id === $user->id || self::isTreasury($user)) {
            return true;
        }
        if ($this->canAct($user, $s)) {
            return true;
        }

        // Personal que ya intervino en la solicitud
        return $s->events()->where('actor_id', $user->id)->exists();
    }

    // ------------------------------------------------------------------
    // Acciones del flujo
    // ------------------------------------------------------------------

    /**
     * El estudiante inicia un trámite.
     * - Sin costo: entra de inmediato al flujo (paso 2).
     * - Con costo: queda "Pendiente de pago" y se genera un ticket en "Mis pagos".
     *   Al pagarse (markPaid) recién entra al flujo.
     *
     * @param  array<int, UploadedFile|array{name:string,size?:int}>  $requirementFiles  indexados por id de requisito
     */
    public function start(Procedure $procedure, User $student, string $description, array $requirementFiles = [], bool $enforceLimits = true): Submission
    {
        $procedure->loadMissing(['steps', 'requirements']);

        if ($enforceLimits && ($block = $this->requestBlock($procedure, $student))) {
            throw ValidationException::withMessages(['procedure' => $block['message']]);
        }

        if ($procedure->steps->isEmpty()) {
            throw ValidationException::withMessages(['procedure' => 'El trámite no tiene pasos configurados.']);
        }

        foreach ($procedure->requirements as $req) {
            if ($req->is_required && empty($requirementFiles[$req->id])) {
                throw ValidationException::withMessages(['files' => "Falta adjuntar: {$req->name}"]);
            }
        }

        $needsPay = $procedure->requires_payment && $procedure->cost > 0;

        return DB::transaction(function () use ($procedure, $student, $description, $requirementFiles, $needsPay) {
            $first = $procedure->steps->first();

            $s = new Submission([
                'code' => $this->nextCode(),
                'procedure_id' => $procedure->id,
                'procedure_code' => $procedure->code,
                'procedure_name' => $procedure->name,
                'procedure_category' => $procedure->category,
                'cost' => $procedure->cost,
                'student_id' => $student->id,
                'faculty_id' => $student->faculty_id,
                'program_id' => $student->program_id,
                'description' => $description,
                'status' => $needsPay ? 'PENDIENTE_PAGO' : 'EN_PROCESO',
                'current_step_index' => 0,
                'payment_status' => $needsPay ? 'PENDIENTE' : 'NO_REQUIERE',
            ]);
            $s->save();
            $s->setRelation('procedure', $procedure);

            foreach ($procedure->requirements as $req) {
                if (! empty($requirementFiles[$req->id])) {
                    $isPhoto = str_contains(Str::lower($req->name), 'foto');
                    $this->attach($s, $requirementFiles[$req->id], 'REQUISITO', $student, $isPhoto ? 'FOTO_CARNET' : 'DOCUMENTO_ACADEMICO', label: $req->name);
                }
            }

            $this->log($s, $first, $student, 'Estudiante', 'CREACION', "Solicitud registrada. Justificación: \"{$description}\"");

            if ($needsPay) {
                $ticket = Payment::create([
                    'code' => Payment::nextCode(),
                    'submission_id' => $s->id,
                    'user_id' => $student->id,
                    'procedure_id' => $procedure->id,
                    'concept' => $procedure->payment_concept ?: "Derecho de trámite: {$procedure->name}",
                    'amount' => $procedure->cost,
                    'status' => 'PENDIENTE',
                    'expires_at' => BusinessDays::add(now(), Payment::DAYS_TO_PAY),
                ]);
                $s->save();
                $this->notify($student->id, 'Tienes un pago pendiente',
                    sprintf('Tu trámite %s tiene un pago pendiente de S/ %s. Págalo en línea («Mis pagos») o en Caja UDH dando tu código de estudiante, hasta el %s; si no, la solicitud se anula.',
                        $s->code, number_format($ticket->amount, 2), $ticket->expires_at->locale('es')->isoFormat('dddd D [de] MMMM')),
                    'PAYMENT', $s->id);

                return $s->fresh();
            }

            $this->enterWorkflow($s, $student);
            $s->save();

            return $s->fresh();
        });
    }

    /** Horas que deben pasar para volver a solicitar el mismo trámite. */
    public const HOURS_BETWEEN_REQUESTS = 24;

    /**
     * ¿El estudiante puede solicitar este trámite ahora?
     * No puede si: ya tiene uno igual esperando pago, o lo solicitó / pagó hace menos de 24 horas.
     *
     * @return array{message:string, available_at:?string, submission_id:int, submission_code:string}|null
     */
    public function requestBlock(Procedure $procedure, User $student): ?array
    {
        $same = Submission::with('payments')->where('student_id', $student->id)
            ->where(fn ($q) => $q->where('procedure_id', $procedure->id)->orWhere('procedure_code', $procedure->code))
            ->where('status', '!=', 'VENCIDO')
            ->latest('id')->get();

        if ($pending = $same->firstWhere('status', 'PENDIENTE_PAGO')) {
            return [
                'message' => "Ya tienes una solicitud de este trámite esperando pago (expediente {$pending->code}). Págala en «Mis pagos» o en Caja UDH con tu código de estudiante.",
                'available_at' => null,
                'submission_id' => $pending->id,
                'submission_code' => $pending->code,
            ];
        }

        // Última actividad: cuándo lo solicitó o cuándo lo pagó (lo más reciente)
        $last = $same->map(fn ($s) => [
            's' => $s,
            'at' => collect([$s->created_at, $s->payments->max('paid_at')])->filter()->max(),
        ])->sortByDesc('at')->first();

        if ($last && $last['at']->gt(now()->subHours(self::HOURS_BETWEEN_REQUESTS))) {
            $availableAt = $last['at']->copy()->addHours(self::HOURS_BETWEEN_REQUESTS);
            $wait = now()->diff($availableAt);
            $waitText = $wait->h > 0 || $wait->d > 0 ? ($wait->d * 24 + $wait->h).' h '.$wait->i.' min' : max(1, $wait->i).' min';

            return [
                'message' => "Ya solicitaste este trámite hace menos de 24 horas (expediente {$last['s']->code}). Por seguridad podrás volver a solicitarlo en {$waitText}, desde el ".$availableAt->locale('es')->isoFormat('D [de] MMMM, h:mm a'),
                'available_at' => $availableAt->toIso8601String(),
                'submission_id' => $last['s']->id,
                'submission_code' => $last['s']->code,
            ];
        }

        return null;
    }

    /**
     * Anula los tickets que pasaron su fecha límite (5 días hábiles) sin pagarse.
     * La solicitud queda "Vencida" y deja de mostrarse al estudiante: tendrá que volver a solicitar.
     */
    public function expireOverdue(): int
    {
        $overdue = Payment::with('submission')->where('status', 'PENDIENTE')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())->get();

        foreach ($overdue as $ticket) {
            DB::transaction(function () use ($ticket) {
                $ticket->update(['status' => 'VENCIDO', 'failure_reason' => 'Venció el plazo de pago ('.Payment::DAYS_TO_PAY.' días hábiles).']);
                $s = $ticket->submission;
                if ($s && $s->status === 'PENDIENTE_PAGO') {
                    $s->update(['status' => 'VENCIDO', 'payment_status' => 'VENCIDO', 'current_area' => null, 'finished_at' => now()]);
                    SubmissionEventLogger::system($s, 1, 'VENCIDO',
                        "Solicitud anulada: no se pagó el ticket {$ticket->code} dentro de ".Payment::DAYS_TO_PAY.' días hábiles.');
                    $this->notify($ticket->user_id, 'Solicitud anulada por falta de pago',
                        "Tu solicitud {$s->code} ({$s->procedure_name}) se anuló porque el ticket {$ticket->code} no se pagó a tiempo. Si aún lo necesitas, vuelve a solicitarlo.",
                        'ALERT', null);
                }
            });
        }

        return $overdue->count();
    }

    /**
     * Confirma el pago de un ticket (lo llama la pasarela o el simulador) y hace avanzar la solicitud.
     * Es idempotente: si el ticket ya estaba pagado no hace nada.
     */
    public function markPaid(Payment $payment, string $method, ?string $reference, string $transactionCode, string $gateway, ?string $gatewayChargeId = null, ?array $card = null): Submission
    {
        return DB::transaction(function () use ($payment, $method, $reference, $transactionCode, $gateway, $gatewayChargeId, $card) {
            $payment = Payment::lockForUpdate()->find($payment->id);
            $s = $payment->submission()->with('procedure.steps')->first();
                if ($payment->status === 'PAGADO') {
                return $s;
            }

            $payment->update([
                'status' => 'PAGADO',
                'method' => $method,
                'reference' => $reference,
                'transaction_code' => $transactionCode,
                'gateway' => $gateway,
                'gateway_charge_id' => $gatewayChargeId,
                'failure_reason' => null,
                'paid_at' => now(),
                ...array_filter($card ?? [], fn ($v) => $v !== null && $v !== ''),
            ]);

            $s->fill([
                'payment_status' => 'PAGADO',
                'payment_method' => $method,
                'payment_operation' => $transactionCode,
                'payment_amount' => $payment->amount,
                'payment_date' => now()->toDateString(),
            ]);

            $label = Payment::METHODS[$method] ?? $method;
            $first = $s->procedure?->steps->first();
            $s->events()->create([
                'step_number' => $first?->step_number ?? 1,
                'step_name' => 'Pago del derecho de trámite',
                'actor_id' => $payment->user_id,
                'actor_name' => match ($gateway) { 'CULQI' => 'Pasarela Culqi', 'CAJA' => 'Caja UDH', default => 'Pasarela de pagos (prueba)' },
                'actor_role' => 'SISTEMA',
                'actor_area' => $gateway === 'CAJA' ? 'Pago en caja' : 'Pago en línea',
                'action' => 'PAGO_REALIZADO',
                'comment' => $gateway === 'CAJA'
                    ? sprintf('Pago en Caja UDH confirmado: boleta %s, S/ %s.', $reference, number_format($payment->amount, 2))
                    : sprintf('Pago confirmado: %s %s, S/ %s. Ticket %s, transacción %s.', $payment->fresh()->methodDetail() ?: $label, $method === 'TARJETA' ? '' : $reference, number_format($payment->amount, 2), $payment->code, $transactionCode),
            ]);

            if ($s->status === 'PENDIENTE_PAGO') {
                $s->status = 'EN_PROCESO';
                $this->enterWorkflow($s, $payment->user);
            }
            $s->save();

            $next = $this->currentStep($s);
            $this->notify($payment->user_id, 'Pago confirmado',
                "Recibimos tu pago de S/ ".number_format($payment->amount, 2)." (ticket {$payment->code}). Tu trámite {$s->code} ya está en proceso".($next ? ": {$next->name}." : '.'),
                'SUCCESS', $s->id);

            return $s->fresh();
        });
    }

    /** Pasa la solicitud del paso 1 (estudiante) al primer área responsable. */
    protected function enterWorkflow(Submission $s, User $student): void
    {
        $steps = $s->procedure->steps;
        $s->current_step_index = $steps->count() > 1 ? 1 : 0;

        if ($steps->count() <= 1) {
            $this->finish($s, $student, $steps->count());

            return;
        }
        $this->resolveAssignment($s);
        $this->startStepClock($s);

        $next = $this->currentStep($s);
        $this->notify($student->id, 'Solicitud en proceso',
            "Tu solicitud {$s->code} ({$s->procedure_name}) está en: ".($next?->name ?? 'revisión').' ('.$this->areaLabel($next?->area, $s).').', 'INFO', $s->id);
    }

    /**
     * El área responsable aprueba su paso y la solicitud pasa al siguiente.
     *  - Pasos intermedios: el archivo es opcional (si se adjunta, el estudiante lo ve).
     *    Se envía al correo del estudiante SOLO si el área lo pide ($sendMail).
     *  - Último paso: debe adjuntar el documento que se entrega al estudiante (siempre se envía por correo).
     */
    public function approve(Submission $s, User $actor, ?string $comment = null, UploadedFile|array|null $file = null, bool $sendMail = false): Submission
    {
        $this->guardActionable($s, $actor);
        $s->loadMissing(['procedure.steps', 'program', 'faculty']);
        $step = $this->currentStep($s);
        $steps = $s->procedure->steps->values();
        $isLast = $s->current_step_index >= $steps->count() - 1;
        $areaLabel = $this->areaLabel($step->area, $s);

        if ($isLast && ! $file) {
            throw ValidationException::withMessages(['file' => 'Es el último paso: adjunta el documento que se entregará al estudiante.']);
        }

        $doc = null;
        $result = DB::transaction(function () use ($s, $actor, $comment, $file, $step, $steps, $isLast, $areaLabel, $sendMail, &$doc) {
            $doc = $file ? $this->attach($s, $file, $isLast ? 'DOCUMENTO_FINAL' : 'DOCUMENTO', $actor, $areaLabel) : null;
            $docName = $doc?->name;

            $action = $step->action_required === 'VERIFICAR_PAGO' ? 'PAGO_VALIDADO' : 'APROBADO';
            if ($action === 'PAGO_VALIDADO') {
                $s->payment_status = 'VERIFICADO';
            }
            $this->log($s, $step, $actor, $areaLabel, $action, $comment ?: "Aprobado por {$areaLabel}.", $docName);

            $s->observation = null; // si había sido derivada, la nota ya se atendió
            if ($isLast) {
                $this->finish($s, $actor, $steps->count(), $docName);
                $s->step_started_at = null;
                $s->step_due_at = null;
            } else {
                $s->current_step_index++;
                $this->resolveAssignment($s);
                $this->startStepClock($s);
                $next = $steps->get($s->current_step_index);
                $this->notify($s->student_id, 'Avance de tu trámite',
                    "Tu trámite {$s->code} pasó a ".$this->areaLabel($next->area, $s).'.'
                    .($docName ? ' Se adjuntó un documento'.($sendMail ? ' (también te lo enviamos por correo).' : '.') : ''), 'INFO', $s->id);
            }

            $s->save();

            return $s->fresh();
        });

        // Último paso: el documento se envía siempre al correo del estudiante.
        // Pasos intermedios: solo si el área marcó "Enviar al correo del estudiante".
        if ($doc?->path && ($isLast || $sendMail)) {
            $this->mailDocument($result, $doc, $step->area, $actor, $isLast);
        }

        return $result;
    }

    /** Correo institucional del área que atiende un paso. */
    public function areaEmail(?string $area, Submission $s, ?User $actor = null): ?string
    {
        $s->loadMissing(['program', 'faculty']);

        return match ($area) {
            'PROGRAMA_ACADEMICO' => $s->program?->director_email,
            'FACULTAD' => $s->faculty?->dean_email,
            default => $this->areas()->get($area)?->email,
        } ?: $actor?->email;
    }

    /**
     * Envía al estudiante el documento que subió un área. Si el correo falla, el trámite sigue igual.
     *  - Si el área conectó su correo: sale DESDE su cuenta (queda en sus "Enviados").
     *  - Si no: sale con la cuenta general y el área recibe una copia oculta.
     */
    public function mailDocument(Submission $s, SubmissionDocument $doc, ?string $area, User $actor, bool $isFinal): bool
    {
        $s->loadMissing(['student', 'program', 'faculty']);
        $to = $s->student?->email;
        if (! $to) {
            return false;
        }
        $accounts = app(MailAccounts::class);
        $sender = $accounts->senderFor($area, $s);
        $areaName = $this->areaLabel($area, $s);
        $areaEmail = $sender?->mailAddress() ?: $actor->email;
        $own = $sender ? $accounts->mailerFor($sender) : null;

        try {
            $mail = new DocumentoTramiteMail($s, $doc, $areaName.' · UDH', $areaEmail, $isFinal, ownAccount: (bool) $own);
            if ($own) {
                $own->to($to)->send($mail);
                $how = "desde {$areaEmail} (queda en sus «Enviados»)";
            } else {
                $pending = Mail::to($to);
                if ($areaEmail && config('mail.copy_to_area') && $areaEmail !== config('mail.from.address')) {
                    $pending->bcc($areaEmail);
                }
                $pending->send($mail);
                $how = 'desde la cuenta general'.($areaEmail ? " (copia a {$areaEmail})" : '');
            }
            SubmissionEventLogger::write($s, $this->currentStep($s)?->step_number ?? 1, 'Envío por correo', $actor, $areaName, 'CORREO_ENVIADO',
                "Documento «{$doc->name}» enviado al correo {$to} {$how}.");

            return true;
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar el correo del expediente {$s->code}: ".$e->getMessage());

            return false;
        }
    }

    protected function finish(Submission $s, User $actor, int $stepCount, ?string $docName = null): void
    {
        $s->status = 'FINALIZADO';
        $s->current_area = null;
        $s->channel_id = null;
        $s->finished_at = now();

        SubmissionEventLogger::system($s, $stepCount, 'FINALIZADO',
            $docName ? "Trámite finalizado. Documento entregado: {$docName}." : 'Trámite finalizado.');

        $this->notify($s->student_id, '¡Trámite finalizado!',
            "Tu trámite {$s->code} ({$s->procedure_name}) finalizó.".($docName ? ' Ya puedes descargar tu documento.' : ''), 'SUCCESS', $s->id);
    }

    /** El área observa la solicitud: vuelve al estudiante para que subsane. */
    public function observe(Submission $s, User $actor, string $reason, string $detail): Submission
    {
        $this->guardActionable($s, $actor);
        $s->loadMissing(['procedure.steps', 'program', 'faculty']);
        $step = $this->currentStep($s);
        if (! $step->can_observe && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['action' => 'Este paso no permite observaciones.']);
        }
        $areaLabel = $this->areaLabel($step->area, $s);

        $s->status = 'OBSERVADO';
        $s->observation = [
            'reason' => $reason,
            'detail' => $detail,
            'area' => $areaLabel,
            'by' => $actor->name,
            'at' => now()->toIso8601String(),
            'step_number' => $step->step_number,
        ];
        $s->save();

        $this->log($s, $step, $actor, $areaLabel, 'OBSERVADO', "OBSERVACIÓN: {$reason}. Detalle: {$detail}");
        $this->notify($s->student_id, 'Observación en tu solicitud',
            "Tu trámite {$s->code} tiene una observación de {$areaLabel}: {$reason}. Ingresa para subsanar.", 'WARNING', $s->id);

        return $s->fresh();
    }

    /** El estudiante subsana la observación: la solicitud vuelve al mismo paso. */
    public function correct(Submission $s, User $student, string $comment, UploadedFile|array|null $file = null): Submission
    {
        if ($s->student_id !== $student->id || $s->status !== 'OBSERVADO') {
            throw ValidationException::withMessages(['action' => 'Esta solicitud no tiene observaciones pendientes.']);
        }
        $s->loadMissing('procedure.steps');
        $area = $s->observation['area'] ?? 'el área correspondiente';
        $docName = $file ? $this->attach($s, $file, 'SUBSANACION', $student, 'DOCUMENTO_ACADEMICO')->name : null;

        $s->status = 'EN_PROCESO';
        $s->observation = null;
        $this->resolveAssignment($s);
        $this->startStepClock($s);
        $s->save();

        $step = $this->currentStep($s);
        SubmissionEventLogger::write($s, $step?->step_number ?? 1, 'Subsanación de observación', $student, 'Estudiante', 'SUBSANADO',
            "Subsanación enviada: \"{$comment}\". Se reanuda la evaluación en {$area}.", $docName);

        return $s->fresh();
    }

    /** Áreas anteriores a las que se puede devolver la solicitud (no incluye al estudiante). */
    public function returnTargets(Submission $s): array
    {
        $s->loadMissing(['procedure.steps', 'program', 'faculty']);
        $targets = [];
        foreach ($s->procedure->steps->values() as $i => $step) {
            if ($i >= $s->current_step_index) {
                break;
            }
            if ($step->area === 'ESTUDIANTE') {
                continue;
            }
            $targets[] = ['index' => $i, 'step_number' => $step->step_number, 'name' => $step->name, 'area' => $step->area, 'label' => $this->areaLabel($step->area, $s)];
        }

        return $targets;
    }

    /**
     * Derivar: el área devuelve la solicitud a un área ANTERIOR del flujo, con sus observaciones,
     * para que corrija lo que corresponda. Desde ahí el flujo continúa normalmente.
     * (Para pedirle algo al estudiante se usa "Observar").
     */
    public function derive(Submission $s, User $actor, int $targetIndex, string $reason, ?string $detail = null, UploadedFile|array|null $file = null): Submission
    {
        $this->guardActionable($s, $actor);
        $s->loadMissing(['procedure.steps', 'program', 'faculty']);
        $step = $this->currentStep($s);
        if (! $step->can_return && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['action' => 'Este paso no permite devolver la solicitud a otra área.']);
        }
        $target = collect($this->returnTargets($s))->firstWhere('index', $targetIndex);
        if (! $target) {
            throw ValidationException::withMessages(['target' => 'Elige un área anterior del flujo.']);
        }
        $fromLabel = $this->areaLabel($step->area, $s);

        return DB::transaction(function () use ($s, $actor, $step, $target, $reason, $detail, $file, $fromLabel) {
            $docName = $file ? $this->attach($s, $file, 'DOCUMENTO', $actor, $fromLabel)->name : null;
            $this->log($s, $step, $actor, $fromLabel, 'DERIVADO',
                "Devuelto a {$target['label']}: {$reason}.".($detail ? " Detalle: {$detail}" : ''), $docName);

            $s->current_step_index = $target['index'];
            $s->status = 'EN_PROCESO';
            $s->observation = [
                'type' => 'DERIVADO',
                'reason' => $reason,
                'detail' => $detail,
                'area' => $fromLabel,
                'to' => $target['label'],
                'by' => $actor->name,
                'at' => now()->toIso8601String(),
            ];
            $this->resolveAssignment($s);
            $this->startStepClock($s);
            $s->save();

            $this->notify($s->student_id, 'Tu trámite volvió a una etapa anterior',
                "{$fromLabel} devolvió tu trámite {$s->code} a {$target['label']} para una corrección. No tienes que hacer nada: seguirá su curso.", 'INFO', $s->id);

            return $s->fresh();
        });
    }

    /** Envía al estudiante, por correo, un documento que subió un área (botón «Enviar al correo»). */
    public function sendDocumentByMail(Submission $s, SubmissionDocument $doc, User $actor): void
    {
        if (! $doc->path || ! in_array($doc->type, ['DOCUMENTO', 'DOCUMENTO_FINAL'], true)) {
            throw ValidationException::withMessages(['document' => 'Solo se pueden enviar los documentos que subió un área.']);
        }
        if (! $actor->isAdmin() && $doc->uploaded_by !== $actor->id) {
            throw ValidationException::withMessages(['document' => 'Solo quien subió el documento puede enviarlo por correo.']);
        }
        $area = $this->userArea($actor) ?? $this->currentStep($s->loadMissing('procedure.steps'))?->area;
        if (! $this->mailDocument($s, $doc, $area, $actor, $doc->type === 'DOCUMENTO_FINAL')) {
            throw ValidationException::withMessages(['document' => 'No se pudo enviar el correo. Revisa la configuración de correo e inténtalo de nuevo.']);
        }
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    protected function guardActionable(Submission $s, User $actor): void
    {
        if (! $this->canAct($actor, $s)) {
            throw ValidationException::withMessages(['action' => 'No tienes permiso para atender esta solicitud en su etapa actual.']);
        }
    }

    /** Adjunta un archivo real (UploadedFile) o un documento generado por el sistema (array con name/size). */
    public function attach(
        Submission $s,
        UploadedFile|array $file,
        string $type,
        User $by,
        ?string $category = null,
        ?string $officialNumber = null,
        ?string $label = null,
    ): SubmissionDocument {
        if ($file instanceof UploadedFile) {
            $path = $file->store("expedientes/{$s->code}", 'local');

            return $s->documents()->create([
                'name' => $file->getClientOriginalName(),
                'type' => $type,
                'category' => $category,
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $by->id,
                'uploaded_by_name' => $by->name,
                'official_number' => $officialNumber,
            ]);
        }

        return $s->documents()->create([
            'name' => $file['name'] ?? (Str::slug($label ?? $type, '_').'.pdf'),
            'type' => $type,
            'category' => $category,
            'path' => null, // documento generado: se produce en PDF al descargarlo
            'mime' => 'application/pdf',
            'size_bytes' => $file['size'] ?? 250_000,
            'uploaded_by' => $by->id,
            'uploaded_by_name' => $by->name,
            'official_number' => $officialNumber,
        ]);
    }

    protected function log(Submission $s, ?ProcedureStep $step, User $actor, string $areaLabel, string $action, string $comment, ?string $doc = null): void
    {
        SubmissionEventLogger::write($s, $step?->step_number ?? 1, $step?->name ?? 'Revisión', $actor, $areaLabel, $action, $comment, $doc);
    }

    public function notify(int $userId, string $title, string $message, string $type = 'INFO', ?int $submissionId = null): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'submission_id' => $submissionId,
        ]);
    }

    public function nextCode(): string
    {
        $year = now()->year;
        $n = Submission::where('code', 'like', "UDH-{$year}-%")->count() + 1;
        do {
            $code = sprintf('UDH-%d-%05d', $year, $n++);
        } while (Submission::where('code', $code)->exists());

        return $code;
    }
}
