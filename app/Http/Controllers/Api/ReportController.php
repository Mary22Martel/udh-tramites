<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\Program;
use App\Models\Submission;
use App\Models\User;
use App\Services\WorkflowService;

/** Reporte: trámites por carrera (catálogo disponible y solicitudes registradas). */
class ReportController extends Controller
{
    public function byProgram(WorkflowService $wf)
    {
        $general = Procedure::where('is_active', true)->where('target_scope', 'TODOS')->count();

        $rows = Program::with('faculty:id,code,name')
            ->withCount([
                'students',
                'submissions as total',
                'submissions as en_proceso' => fn ($q) => $q->where('status', 'EN_PROCESO'),
                'submissions as observado' => fn ($q) => $q->where('status', 'OBSERVADO'),
                'submissions as finalizado' => fn ($q) => $q->where('status', 'FINALIZADO'),
                'submissions as rechazado' => fn ($q) => $q->where('status', 'RECHAZADO'),
            ])
            ->orderByRaw("CASE level WHEN 'PREGRADO' THEN 1 WHEN 'SEGUNDA_ESPECIALIDAD' THEN 2 WHEN 'MAESTRIA' THEN 3 ELSE 4 END")
            ->orderBy('name')->get()
            ->map(function (Program $p) use ($wf) {
                // Exclusivos: trámites que solo tiene esta carrera
                $own = Procedure::where('is_active', true)->where('target_scope', 'PROGRAMA')
                    ->whereHas('programs', fn ($q) => $q->whereKey($p->id))
                    ->has('programs', '=', 1)->count();
                $fake = new User(['program_id' => $p->id, 'faculty_id' => $p->faculty_id]);
                $revenue = (float) \App\Models\Payment::where('status', 'PAGADO')
                    ->whereHas('submission', fn ($q) => $q->where('program_id', $p->id))->sum('amount');

                return [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'faculty' => $p->faculty?->name,
                    'level' => Program::LEVELS[$p->level] ?? $p->level,
                    'modalities' => $p->modalities,
                    'students' => $p->students_count,
                    'own_procedures' => $own,
                    'available_procedures' => $wf->availableProceduresFor($fake)->count(),
                    'total' => $p->total,
                    'en_proceso' => $p->en_proceso,
                    'observado' => $p->observado,
                    'finalizado' => $p->finalizado,
                    'rechazado' => $p->rechazado,
                    'revenue' => $revenue,
                ];
            });

        $topByProgram = Submission::selectRaw('program_id, procedure_name, count(*) as total')
            ->groupBy('program_id', 'procedure_name')->get()
            ->groupBy('program_id')
            ->map(fn ($g) => $g->sortByDesc('total')->take(5)->values()->map->only(['procedure_name', 'total']));

        return ['general_procedures' => $general, 'data' => $rows, 'top_by_program' => $topByProgram];
    }
}
