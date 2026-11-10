<?php

namespace App\Services;

use App\Models\Area;
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
            'PROGRAMA_ACADEMICO' => $s?->program ? 'Programa de '.$s->program->name : 'Programa Académico del estudiante',
            'FACULTAD' => $s?->faculty ? 'Decanato - '.$s->faculty->name : 'Decanato de la Facultad del estudiante',
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

    public function canAct(User $user, Submission $s): bool
    {
        return $s->status === 'EN_PROCESO' && $this->tasksQuery($user)->whereKey($s->id)->exists();
    }

    public function canView(User $user, Submission $s): bool
    {
        if ($user->isAdmin() || $s->student_id === $user->id) {
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
     *
     * @param  array<int, UploadedFile|array{name:string,size?:int}>  $requirementFiles  indexados por id de requisito
     * @param  array{method:string,operation:string,date?:string}|null  $payment
     */
    public function start(
        Procedure $procedure,
        User $student,
        string $description,
        array $requirementFiles = [],
        ?array $payment = null,
        UploadedFile|array|null $voucher = null,
        ?\App\Models\Payment $onlinePayment = null,
    ): Submission {
        $procedure->loadMissing(['steps', 'requirements']);

        if ($procedure->steps->isEmpty()) {
            throw ValidationException::withMessages(['procedure' => 'El trámite no tiene pasos configurados.']);
        }

        foreach ($procedure->requirements as $req) {
            if ($req->is_required && empty($requirementFiles[$req->id])) {
                throw ValidationException::withMessages(['files' => "Falta adjuntar: {$req->name}"]);
            }
        }

        if ($onlinePayment) {
            // Pago hecho en la pasarela: ya viene confirmado
            $payment = ['method' => $onlinePayment->method, 'operation' => $onlinePayment->transaction_code, 'date' => $onlinePayment->paid_at?->toDateString()];
        }

        if ($procedure->requires_payment && (empty($payment['method']) || empty($payment['operation']))) {
            throw ValidationException::withMessages(['payment' => 'Debe registrar el método y número de operación del pago.']);
        }

        return DB::transaction(function () use ($procedure, $student, $description, $requirementFiles, $payment, $voucher, $onlinePayment) {
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
                'status' => 'EN_PROCESO',
                'current_step_index' => 0,
                'payment_status' => $procedure->requires_payment ? 'PENDIENTE' : 'NO_REQUIERE',
                'payment_method' => $payment['method'] ?? null,
                'payment_operation' => $payment['operation'] ?? null,
                'payment_amount' => $payment ? $procedure->cost : null,
                'payment_date' => $payment ? ($payment['date'] ?? now()->toDateString()) : null,
            ]);
            $s->save();
            $s->setRelation('procedure', $procedure);

            foreach ($procedure->requirements as $req) {
                if (! empty($requirementFiles[$req->id])) {
                    $isPhoto = str_contains(Str::lower($req->name), 'foto');
                    $this->attach($s, $requirementFiles[$req->id], 'REQUISITO', $student, $isPhoto ? 'FOTO_CARNET' : 'DOCUMENTO_ACADEMICO', label: $req->name);
                }
            }
            if ($voucher) {
                $this->attach($s, $voucher, 'COMPROBANTE_PAGO', $student, 'COMPROBANTE_PAGO', label: 'Comprobante de pago');
            }

            $this->log($s, $first, $student, 'Estudiante', 'CREACION', "Solicitud registrada. Justificación: \"{$description}\"");
            if ($payment && ! $onlinePayment) {
                $this->log($s, $first, $student, 'Estudiante', 'PAGO_REGISTRADO',
                    sprintf('Pago registrado vía %s con N° de operación %s por S/ %s.', $payment['method'], $payment['operation'], number_format($procedure->cost, 2)));
            }

            // El paso 1 (solicitud del estudiante) queda cumplido: avanza al paso 2
            $s->current_step_index = $procedure->steps->count() > 1 ? 1 : 0;

            if ($onlinePayment) {
                // Pago confirmado por la pasarela. Igual pasa por Tesorería, que lo valida en su bandeja.
                $onlinePayment->update(['submission_id' => $s->id]);
                $label = \App\Models\Payment::METHODS[$onlinePayment->method] ?? $onlinePayment->method;
                $this->log($s, $first, $student, 'Estudiante', 'PAGO_REGISTRADO', sprintf(
                    'Pago en línea realizado: %s %s, S/ %s, código de transacción %s. Pendiente de validación por Tesorería.',
                    $label, $onlinePayment->reference, number_format($onlinePayment->amount, 2), $onlinePayment->transaction_code));
            }

            if ($s->current_step_index >= $procedure->steps->count()) {
                $this->finish($s, $student, $procedure->steps->count());
            } else {
                $this->resolveAssignment($s);
            }
            $s->save();

            $next = $this->currentStep($s);
            $this->notify($student->id, 'Solicitud enviada con éxito',
                "Tu solicitud {$s->code} ({$procedure->name}) fue recibida y está en: ".($next?->name ?? 'revisión').'.', 'INFO', $s->id);

            return $s->fresh();
        });
    }

    /** El área responsable aprueba su paso y la solicitud pasa al siguiente. */
    public function approve(
        Submission $s,
        User $actor,
        ?string $comment = null,
        UploadedFile|array|null $file = null,
        ?string $officialNumber = null,
    ): Submission {
        $this->guardActionable($s, $actor);
        $s->loadMissing(['procedure.steps', 'program', 'faculty']);
        $step = $this->currentStep($s);
        $steps = $s->procedure->steps->values();
        $areaLabel = $this->areaLabel($step->area, $s);

        return DB::transaction(function () use ($s, $actor, $comment, $file, $officialNumber, $step, $steps, $areaLabel) {
            $docType = $this->documentTypeFor($step->document_requirement);
            $docName = null;

            if ($docType) {
                $officialNumber = $officialNumber ?: $this->suggestOfficialNumber($s, $step);
                $label = (ProcedureStep::DOCUMENTS[$step->document_requirement] ?? 'Documento').' '.$officialNumber;
                $doc = $this->attach($s, $file ?? ['name' => Str::slug($label, '_').'.pdf', 'size' => 380_000], $docType, $actor, $docType, $officialNumber, $label);
                $docName = $doc->name;
            } elseif ($file) {
                $docName = $this->attach($s, $file, 'ANEXO', $actor, 'DOCUMENTO_ACADEMICO')->name;
            }

            $action = match (true) {
                $step->action_required === 'VERIFICAR_PAGO' => 'PAGO_VALIDADO',
                $docType === 'OFICIO' => 'OFICIO_ADJUNTADO',
                $docType === 'RESOLUCION' => 'RESOLUCION_EMITIDA',
                $docType === 'CONSTANCIA' => 'CONSTANCIA_EMITIDA',
                default => 'APROBADO',
            };
            if ($action === 'PAGO_VALIDADO') {
                $s->payment_status = 'VERIFICADO';
            }

            $this->log($s, $step, $actor, $areaLabel, $action,
                $comment ?: "Etapa \"{$step->name}\" aprobada por {$actor->name}.", $docName);

            $nextIndex = $s->current_step_index + 1;

            if ($nextIndex >= $steps->count()) {
                $this->finish($s, $actor, $steps->count());
            } else {
                $s->current_step_index = $nextIndex;
                $this->resolveAssignment($s);
                $next = $steps->get($nextIndex);
                $this->notify($s->student_id, 'Avance de tu trámite',
                    "Tu trámite {$s->code} avanzó a la etapa: {$next->name} (".$this->areaLabel($next->area, $s).').', 'INFO', $s->id);
            }

            $s->save();

            return $s->fresh();
        });
    }

    protected function finish(Submission $s, User $actor, int $stepCount): void
    {
        $s->status = 'FINALIZADO';
        $s->current_area = null;
        $s->channel_id = null;
        $s->finished_at = now();
        do {
            $code = 'DOC-UDH-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (Submission::where('final_document_code', $code)->exists());
        $s->final_document_code = $code;

        $this->attach($s, ['name' => Str::slug($s->procedure_name, '_').'_'.$s->code.'.pdf', 'size' => 495_000],
            'CONSTANCIA_FINAL', $actor, 'CONSTANCIA_FINAL', $code, 'Documento final');

        SubmissionEventLogger::system($s, $stepCount, 'FINALIZADO',
            "Trámite culminado con éxito. Se generó el documento oficial {$code} con código de verificación QR.");

        $this->notify($s->student_id, '¡Trámite culminado!',
            "Tu trámite {$s->code} ({$s->procedure_name}) finalizó. Ya puedes descargar tu documento oficial.", 'SUCCESS', $s->id);
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
        $s->save();

        $step = $this->currentStep($s);
        SubmissionEventLogger::write($s, $step?->step_number ?? 1, 'Subsanación de observación', $student, 'Estudiante', 'SUBSANADO',
            "Subsanación enviada: \"{$comment}\". Se reanuda la evaluación en {$area}.", $docName);

        return $s->fresh();
    }

    /** Rechazo / denegación definitiva. */
    public function reject(Submission $s, User $actor, string $reason): Submission
    {
        $this->guardActionable($s, $actor);
        $s->loadMissing(['procedure.steps', 'program', 'faculty']);
        $step = $this->currentStep($s);
        if (! $step->can_reject && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['action' => 'Este paso no permite denegar la solicitud.']);
        }
        $areaLabel = $this->areaLabel($step->area, $s);

        $s->status = 'RECHAZADO';
        $s->current_area = null;
        $s->channel_id = null;
        $s->finished_at = now();
        $s->save();

        $this->log($s, $step, $actor, $areaLabel, 'RECHAZADO', "SOLICITUD DENEGADA: {$reason}.");
        $this->notify($s->student_id, 'Solicitud denegada',
            "Tu solicitud {$s->code} fue denegada por {$areaLabel}. Motivo: {$reason}", 'ALERT', $s->id);

        return $s->fresh();
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

    public function documentTypeFor(?string $requirement): ?string
    {
        return match ($requirement) {
            'OFICIO_PA' => 'OFICIO',
            'RESOLUCION_DECANAL' => 'RESOLUCION',
            'CONSTANCIA_OFICIAL' => 'CONSTANCIA',
            'INFORME_TECNICO' => 'INFORME',
            default => null,
        };
    }

    public function suggestOfficialNumber(Submission $s, ProcedureStep $step): string
    {
        $s->loadMissing(['program', 'faculty']);
        $n = str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $year = now()->year;

        return match ($step->document_requirement) {
            'OFICIO_PA' => "OFICIO N° {$n}-{$year}-PA-".($s->program?->code ?? 'UDH').'-UDH',
            'RESOLUCION_DECANAL' => "RESOLUCIÓN N° {$n}-{$year}-D-".($s->faculty?->code ?? 'FAC').'-UDH',
            'CONSTANCIA_OFICIAL' => "CONSTANCIA N° {$n}-{$year}-DMR-UDH",
            default => "INFORME N° {$n}-{$year}-UDH",
        };
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
