<?php

namespace App\Support;

use App\Models\ProcedureStep;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\WorkflowService;

/** Convierte modelos en el JSON que consume el frontend React. */
class Presenter
{
    public static function user(User $u, bool $withCounts = false): array
    {
        $u->loadMissing(['program:id,code,name,faculty_id', 'faculty:id,code,name', 'area:id,code,name']);
        $data = [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'role_label' => $u->role_label,
            'initials' => collect(explode(' ', preg_replace('/^(Ing|Lic|Mg|Dr|Dra|Arq|Abog)\.\s*/u', '', $u->name)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join(''),
            'student_code' => $u->student_code,
            'dni' => $u->dni,
            'phone' => $u->phone,
            'current_semester' => $u->current_semester,
            'academic_state' => $u->academic_state,
            'job_title' => $u->job_title,
            'department' => $u->department,
            'is_active' => $u->is_active,
            'program' => $u->program?->only(['id', 'code', 'name']),
            'faculty' => $u->faculty?->only(['id', 'code', 'name']),
            'area' => $u->area?->only(['id', 'code', 'name']),
        ];

        if ($withCounts) {
            $wf = app(WorkflowService::class);
            $data['counts'] = [
                'tasks' => $u->isStudent() ? 0 : $wf->tasksQuery($u)->count(),
                'observed' => $u->isStudent() ? Submission::where('student_id', $u->id)->where('status', 'OBSERVADO')->count() : 0,
                'payments' => $u->isStudent() ? \App\Models\Payment::where('user_id', $u->id)->where('status', 'PENDIENTE')->count() : 0,
                'pending_payments' => WorkflowService::isTreasury($u) ? \App\Models\Payment::where('status', 'PENDIENTE')->count() : 0,
                'notifications' => UserNotification::where('user_id', $u->id)->whereNull('read_at')->count(),
            ];
        }

        return $data;
    }

    /** Resumen para listas y bandejas. */
    public static function submissionRow(Submission $s, ?User $viewer = null): array
    {
        $s->loadMissing(['student:id,name,student_code,email', 'program:id,code,name', 'faculty:id,code,name', 'procedure.steps', 'channel:id,name', 'payments']);
        $wf = app(WorkflowService::class);
        $step = $wf->currentStep($s);
        $total = $s->procedure?->steps->count() ?? 0;

        return [
            'id' => $s->id,
            'code' => $s->code,
            'procedure_id' => $s->procedure_id,
            'procedure_code' => $s->procedure_code,
            'procedure_name' => $s->procedure_name,
            'procedure_category' => $s->procedure_category,
            'cost' => $s->cost,
            'status' => $s->status,
            'payment_status' => $s->payment_status,
            'student' => $s->student?->only(['id', 'name', 'student_code', 'email']),
            'program' => $s->program?->only(['id', 'code', 'name']),
            'faculty' => $s->faculty?->only(['id', 'code', 'name']),
            'current_step' => $step && in_array($s->status, ['EN_PROCESO', 'OBSERVADO'], true) ? [
                'number' => $step->step_number,
                'name' => $step->name,
                'area' => $step->area,
                'area_label' => $wf->areaLabel($step->area, $s),
                'action_required' => $step->action_required,
                'days' => $step->days ?: ProcedureStep::DEFAULT_DAYS,
            ] : null,
            // Plazo del área actual (días hábiles configurados en el paso)
            'step_due' => $s->status === 'EN_PROCESO' && $s->step_due_at ? [
                'due_at' => $s->step_due_at->toIso8601String(),
                'started_at' => $s->step_started_at?->toIso8601String(),
                'days_left' => \App\Support\BusinessDays::remaining($s->step_due_at),
                'overdue' => $s->step_due_at->isPast(),
            ] : null,
            'channel' => $s->channel?->name,
            'progress' => $total ? ($s->status === 'FINALIZADO' ? 100 : (int) round(($s->current_step_index / $total) * 100)) : 0,
            'total_steps' => $total,
            'observation' => $s->observation,
            'ticket' => ($t = $s->payments->sortByDesc('id')->first()) ? $t->only(['id', 'code', 'status', 'amount', 'method', 'reference']) + [
                'method_detail' => $t->methodDetail(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'days_left' => $t->status === 'PENDIENTE' && $t->expires_at ? \App\Support\BusinessDays::remaining($t->expires_at) : null,
            ] : null,
            'created_at' => $s->created_at?->toIso8601String(),
            'updated_at' => $s->updated_at?->toIso8601String(),
            'finished_at' => $s->finished_at?->toIso8601String(),
        ];
    }

    /** Detalle completo: pasos con su estado, documentos e historial. */
    public static function submissionDetail(Submission $s, User $viewer): array
    {
        $s->loadMissing(['documents', 'events', 'procedure.requirements']);
        $wf = app(WorkflowService::class);
        $row = self::submissionRow($s, $viewer);
        $steps = $s->procedure?->steps->values() ?? collect();

        $row['description'] = $s->description;
        $row['payment'] = [
            'method' => $s->payment_method,
            'operation' => $s->payment_operation,
            'amount' => $s->payment_amount,
            'date' => $s->payment_date?->toDateString(),
        ];
        $row['steps'] = $steps->map(function (ProcedureStep $st, $i) use ($s, $wf) {
            $state = match (true) {
                $s->status === 'FINALIZADO' => 'done',
                $s->status === 'PENDIENTE_PAGO' => $i === 0 ? 'done' : 'pending',
                $i < $s->current_step_index => 'done',
                $i === $s->current_step_index && $s->status === 'OBSERVADO' => 'observed',
                $i === $s->current_step_index && $s->status === 'RECHAZADO' => 'rejected',
                $i === $s->current_step_index => 'current',
                default => 'pending',
            };

            return [
                'number' => $st->step_number,
                'name' => $st->name,
                'description' => $st->description,
                'area' => $st->area,
                'area_label' => $wf->areaLabel($st->area, $s),
                'action_required' => $st->action_required,
                'can_observe' => $st->can_observe,
                'can_return' => $st->can_return,
                'days' => $st->days ?: ProcedureStep::DEFAULT_DAYS,
                'state' => $state,
            ];
        })->all();
        $row['documents'] = $s->documents->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'type' => $d->type,
            'category' => $d->category,
            'mime' => $d->mime,
            'size_bytes' => $d->size_bytes,
            'has_file' => (bool) $d->path,
            'uploaded_by_name' => $d->uploaded_by_name,
            // El área que subió el documento puede enviarlo al correo del estudiante con un botón
            'can_mail' => $d->path && in_array($d->type, ['DOCUMENTO', 'DOCUMENTO_FINAL'], true)
                && ($viewer->isAdmin() || $d->uploaded_by === $viewer->id),
            'official_number' => $d->official_number,
            'created_at' => $d->created_at?->toIso8601String(),
        ])->all();
        $row['events'] = $s->events->map(fn ($e) => [
            'id' => $e->id,
            'step_number' => $e->step_number,
            'step_name' => $e->step_name,
            'actor_name' => $e->actor_name,
            'actor_role' => $e->actor_role,
            'actor_area' => $e->actor_area,
            'action' => $e->action,
            'comment' => $e->comment,
            'document_name' => $e->document_name,
            'created_at' => $e->created_at?->toIso8601String(),
        ])->all();

        $current = $wf->currentStep($s);
        $canAct = $wf->canAct($viewer, $s);
        $isLast = $current && $s->current_step_index >= $steps->count() - 1;
        $row['permissions'] = [
            'can_act' => $canAct,
            'can_observe' => $canAct && ($current?->can_observe || $viewer->isAdmin()),
            'can_return' => $canAct && ($current?->can_return || $viewer->isAdmin()) && count($returnTargets = $wf->returnTargets($s)) > 0,
            'can_correct' => $s->status === 'OBSERVADO' && $s->student_id === $viewer->id,
            'is_last_step' => $canAct && $isLast,          // debe subir el documento final
            'is_payment_step' => $canAct && $current?->action_required === 'VERIFICAR_PAGO',
        ];
        $row['return_targets'] = $canAct ? $wf->returnTargets($s) : [];

        return $row;
    }
}
