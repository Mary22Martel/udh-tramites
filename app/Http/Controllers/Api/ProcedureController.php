<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\Program;
use App\Services\ProcedureBuilder;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcedureController extends Controller
{
    /** Contexto para mostrar "Programa de X" / "Facultad de Y" al estudiante. */
    protected ?\App\Models\Submission $ctx = null;

    public function __construct(protected WorkflowService $wf, protected ProcedureBuilder $builder) {}

    /** Catálogo: el estudiante ve solo lo disponible para su carrera; el personal ve todo. */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $this->ctx = (new \App\Models\Submission)->setRelation('program', $user->program)->setRelation('faculty', $user->faculty);
            $items = $this->wf->availableProceduresFor($user)->load('requirements', 'steps');
        } else {
            $q = Procedure::with(['requirements', 'steps', 'programs:id,code,name', 'faculties:id,code,name'])
                ->withCount('submissions')
                ->orderBy('name');
            if ($request->filled('scope') && $request->scope !== 'ALL') {
                $q->where('target_scope', $request->scope);
            }
            if ($request->filled('program_id')) {
                $q->whereHas('programs', fn ($w) => $w->whereKey($request->program_id));
            }
            if (! $user->isAdmin()) {
                $q->where('is_active', true);
            }
            $items = $q->get();
        }

        if ($request->filled('category')) {
            $items = $items->where('category', $request->category)->values();
        }
        if ($request->filled('search')) {
            $s = WorkflowService::normalize($request->search);
            $items = $items->filter(fn ($p) => str_contains(WorkflowService::normalize($p->name.' '.$p->code.' '.$p->description), $s))->values();
        }

        return ['data' => $items->map(fn ($p) => $this->present($p))->values()];
    }

    public function show(Request $request, Procedure $procedure)
    {
        $u = $request->user();
        if ($u->isStudent()) {
            $this->ctx = (new \App\Models\Submission)->setRelation('program', $u->program)->setRelation('faculty', $u->faculty);
        }
        $data = $this->present($procedure->load(['requirements', 'steps', 'programs:id,code,name', 'faculties:id,code,name']));
        if ($u->isStudent()) {
            // Regla de seguridad: el mismo trámite no se puede volver a pedir antes de 24 horas
            $data['block'] = app(\App\Services\WorkflowService::class)->requestBlock($procedure, $u);
            $data['days_to_pay'] = \App\Models\Payment::DAYS_TO_PAY;
        }

        return ['data' => $data];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return ['data' => $this->present($this->builder->save($data))];
    }

    public function update(Request $request, Procedure $procedure)
    {
        $data = $this->validated($request, $procedure);

        return ['data' => $this->present($this->builder->save($data, $procedure))];
    }

    public function toggle(Procedure $procedure)
    {
        $procedure->update(['is_active' => ! $procedure->is_active]);

        return ['data' => $this->present($procedure)];
    }

    public function destroy(Procedure $procedure)
    {
        if ($procedure->submissions()->whereIn('status', ['EN_PROCESO', 'OBSERVADO'])->exists()) {
            abort(422, 'No se puede eliminar: hay solicitudes en curso con este trámite. Puede desactivarlo.');
        }
        $procedure->delete();

        return ['ok' => true];
    }

    public function bifurcate(Request $request, Procedure $procedure)
    {
        $program = Program::findOrFail($request->validate(['program_id' => ['required', 'exists:programs,id']])['program_id']);

        return ['data' => $this->present($this->builder->bifurcate($procedure, $program))];
    }

    /** Registro masivo pegando una lista de nombres (uno por línea). */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'names' => ['required', 'string'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'category' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'days' => ['nullable', 'integer', 'min:1'],
        ]);
        $program = ! empty($data['program_id']) ? Program::find($data['program_id']) : null;
        $created = [];
        $skipped = [];
        foreach (preg_split('/\r?\n/', $data['names']) as $name) {
            $name = trim(preg_replace('/^[\d\.\-\)\s]+/', '', $name));
            if ($name === '') {
                continue;
            }
            if ($this->builder->exists($name, $program)) {
                $skipped[] = $name;

                continue;
            }
            $created[] = $this->builder->createDefault($name, [
                'program' => $program,
                'category' => $data['category'] ?? null,
                'cost' => $data['cost'] ?? 5,
                'days' => $data['days'] ?? 3,
            ])->name;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    protected function validated(Request $request, ?Procedure $procedure = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('procedures', 'code')->ignore($procedure?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(Procedure::CATEGORIES)],
            'estimated_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cost' => ['required', 'numeric', 'min:0'],
            'requires_payment' => ['boolean'],
            'payment_concept' => ['nullable', 'string', 'max:255'],
            'target_scope' => ['required', Rule::in(['TODOS', 'FACULTAD', 'PROGRAMA'])],
            'program_ids' => ['array'],
            'program_ids.*' => ['integer', 'exists:programs,id'],
            'faculty_ids' => ['array'],
            'faculty_ids.*' => ['integer', 'exists:faculties,id'],
            'is_active' => ['boolean'],
            'requirements' => ['array'],
            'requirements.*.name' => ['required', 'string', 'max:255'],
            'requirements.*.description' => ['nullable', 'string'],
            'requirements.*.is_required' => ['boolean'],
            'requirements.*.file_types' => ['array'],
            'requirements.*.max_size_mb' => ['integer', 'min:1', 'max:50'],
            'steps' => ['required', 'array', 'min:2'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.area' => ['required', 'string'],
            'steps.*.action_required' => ['required', 'string'],
            'steps.*.document_requirement' => ['nullable', 'string'],
            'steps.*.can_observe' => ['boolean'],
            'steps.*.can_reject' => ['boolean'],
        ], [
            'steps.min' => 'El flujo debe tener al menos 2 pasos (solicitud del estudiante + 1 área).',
        ]);
    }

    protected function present(Procedure $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'category' => $p->category,
            'estimated_days' => $p->estimated_days,
            'cost' => $p->cost,
            'requires_payment' => $p->requires_payment,
            'payment_concept' => $p->payment_concept,
            'target_scope' => $p->target_scope,
            'is_active' => $p->is_active,
            'version' => $p->version,
            'parent_id' => $p->parent_id,
            'programs' => $p->relationLoaded('programs') ? $p->programs->map->only(['id', 'code', 'name'])->values() : [],
            'faculties' => $p->relationLoaded('faculties') ? $p->faculties->map->only(['id', 'code', 'name'])->values() : [],
            'submissions_count' => $p->submissions_count ?? null,
            'requirements' => $p->relationLoaded('requirements') ? $p->requirements->map->only(['id', 'name', 'description', 'is_required', 'file_types', 'max_size_mb'])->values() : [],
            'steps' => $p->relationLoaded('steps') ? $p->steps->map(fn ($s) => $s->only(['id', 'step_number', 'name', 'description', 'area', 'action_required', 'document_requirement', 'can_observe', 'can_reject'])
                + ['area_label' => $this->wf->areaLabel($s->area, $this->ctx)])->values() : [],
        ];
    }
}
