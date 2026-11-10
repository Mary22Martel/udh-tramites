<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\User;

/** Registra eventos en la línea de tiempo (historial) de una solicitud. */
class SubmissionEventLogger
{
    public static function write(Submission $s, int $stepNumber, string $stepName, User $actor, string $areaLabel, string $action, string $comment, ?string $doc = null): void
    {
        $s->events()->create([
            'step_number' => $stepNumber,
            'step_name' => $stepName,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'actor_area' => $areaLabel,
            'action' => $action,
            'comment' => $comment,
            'document_name' => $doc,
        ]);
    }

    public static function system(Submission $s, int $stepNumber, string $action, string $comment): void
    {
        $s->events()->create([
            'step_number' => $stepNumber,
            'step_name' => 'Finalización del trámite',
            'actor_id' => null,
            'actor_name' => 'Sistema de Trámites UDH',
            'actor_role' => 'SISTEMA',
            'actor_area' => 'Sistema central',
            'action' => $action,
            'comment' => $comment,
        ]);
    }
}
