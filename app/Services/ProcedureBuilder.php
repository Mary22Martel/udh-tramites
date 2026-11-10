<?php

namespace App\Services;

use App\Models\Procedure;
use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Crea, edita y clona trámites con sus requisitos y pasos. */
class ProcedureBuilder
{
    public function exists(string $name, ?Program $program): bool
    {
        $norm = WorkflowService::normalize($name);
        $q = Procedure::query();
        $program
            ? $q->where('target_scope', 'PROGRAMA')->whereHas('programs', fn ($w) => $w->whereKey($program->id))
            : $q->where('target_scope', 'TODOS');

        return $q->pluck('name')->contains(fn ($n) => WorkflowService::normalize($n) === $norm);
    }

    public function autoCategory(string $name): string
    {
        $l = WorkflowService::normalize($name);

        return match (true) {
            (bool) preg_match('/chaqueta|uniforme|taller express|congreso|diplomado|uso de clinica|seminario/', $l) => 'Servicios y Extensión',
            (bool) preg_match('/optar el|expedito|expedicion de diploma|bachiller|titulo profesional/', $l) => 'Grados y Títulos',
            (bool) preg_match('/tesis|trabajo de inv|trab\.? ?de inv|proyecto de inv|asesor|jurado|suficiencia prof|suf\. prof|sustent|articulo|objetante/', $l) => 'Investigación y Tesis',
            (bool) preg_match('/ppp|practicas|internado|carta de presentacion/', $l) => 'Prácticas Pre Profesionales',
            (bool) preg_match('/examen|nota|actas/', $l) => 'Evaluaciones y Notas',
            (bool) preg_match('/constancia|certificado|silabo/', $l) => 'Constancias y Certificados',
            default => 'Matrícula y Régimen de Estudios',
        };
    }

    public function nextCode(?Program $program): string
    {
        $prefix = $program ? $program->code : 'TR-UDH';
        $n = Procedure::where('code', 'like', "{$prefix}-%")->count() + 1;
        do {
            $code = sprintf('%s-%03d', $prefix, $n++);
        } while (Procedure::where('code', $code)->exists());

        return $code;
    }

    /** Trámite con flujo estándar (usado por la importación masiva). */
    public function createDefault(string $name, array $o = []): Procedure
    {
        /** @var Program|null $program */
        $program = $o['program'] ?? null;
        $programIds = $o['program_ids'] ?? ($program ? [$program->id] : []);
        if (! $program && count($programIds) === 1) {
            $program = Program::find($programIds[0]);
        }
        $cost = (float) ($o['cost'] ?? 5);
        $category = in_array($o['category'] ?? null, Procedure::CATEGORIES, true) ? $o['category'] : $this->autoCategory($name);

        $flow = $o['flow'] ?? [];
        if (empty($flow)) {
            // El pago se hace en línea (Culqi): Tesorería solo entra al flujo si se pide en "flujo"
            $flow = array_values(array_filter([
                $programIds ? 'PROGRAMA_ACADEMICO' : null,
                'MATRICULA',
            ]));
        }

        $steps = [[
            'name' => 'Solicitud del estudiante',
            'description' => 'El estudiante registra su solicitud y su justificación.',
            'area' => 'ESTUDIANTE', 'action_required' => 'SOLICITUD', 'can_observe' => false, 'can_reject' => false,
        ]];
        $last = count($flow) - 1;
        foreach ($flow as $i => $area) {
            $steps[] = $this->defaultStep($area, $i === $last, $program);
        }

        return $this->save([
            'name' => $name,
            'description' => "Trámite académico de {$name}, gestionado según el Reglamento de Trámites de la Universidad de Huánuco.",
            'category' => $category,
            'estimated_days' => $o['days'] ?? 3,
            'cost' => $cost,
            'requires_payment' => $cost > 0,
            'payment_concept' => $cost > 0 ? "Derecho de trámite: {$name}" : null,
            'target_scope' => $programIds ? 'PROGRAMA' : 'TODOS',
            'program_ids' => $programIds,
            // Por defecto solo se pide la justificación escrita; los archivos se agregan en el constructor si hacen falta.
            'requirements' => [],
            'steps' => $steps,
        ]);
    }

    protected function defaultStep(string $area, bool $isLast, ?Program $program): array
    {
        $base = match ($area) {
            'TESORERIA' => ['name' => 'Tesorería', 'description' => 'Tesorería revisa el pago.', 'action_required' => 'VERIFICAR_PAGO'],
            'PROGRAMA_ACADEMICO' => ['name' => 'Revisión del programa', 'description' => 'El programa académico revisa y aprueba.'],
            'FACULTAD' => ['name' => 'Aprobación de la facultad', 'description' => 'La facultad revisa y aprueba.'],
            'MATRICULA' => ['name' => 'Matrícula', 'description' => 'Matrícula revisa y aprueba.'],
            default => ['name' => 'Revisión del área', 'description' => 'El área responsable revisa y aprueba.'],
        };
        if ($isLast) {
            $base['name'] .= ' · entrega del documento';
            $base['description'] = 'Aprueba y sube el documento final para el estudiante.';
        }

        return $base + ['area' => $area, 'action_required' => 'APROBAR', 'document_requirement' => null];
    }

    /**
     * Crea o actualiza un trámite completo a partir de un arreglo
     * (mismo formato que envía el constructor de trámites del panel).
     */
    public function save(array $data, ?Procedure $procedure = null): Procedure
    {
        return DB::transaction(function () use ($data, $procedure) {
            $program = null;
            if (($data['target_scope'] ?? 'TODOS') === 'PROGRAMA' && ! empty($data['program_ids'])) {
                $program = Program::find($data['program_ids'][0]);
            }

            $attrs = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? $this->autoCategory($data['name']),
                'estimated_days' => (int) ($data['estimated_days'] ?? 3),
                'cost' => (float) ($data['cost'] ?? 0),
                'requires_payment' => (bool) ($data['requires_payment'] ?? ((float) ($data['cost'] ?? 0) > 0)),
                'payment_concept' => $data['payment_concept'] ?? null,
                'target_scope' => $data['target_scope'] ?? 'TODOS',
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];

            if ($procedure) {
                $attrs['version'] = $procedure->version + 1;
                if (! empty($data['code'])) {
                    $attrs['code'] = $data['code'];
                }
                $procedure->update($attrs);
            } else {
                $attrs['code'] = $data['code'] ?? $this->nextCode($program);
                $attrs['parent_id'] = $data['parent_id'] ?? null;
                $procedure = Procedure::create($attrs);
            }

            $procedure->programs()->sync($attrs['target_scope'] === 'PROGRAMA' ? ($data['program_ids'] ?? []) : []);
            $procedure->faculties()->sync($attrs['target_scope'] === 'FACULTAD' ? ($data['faculty_ids'] ?? []) : []);

            if (array_key_exists('requirements', $data)) {
                $procedure->requirements()->delete();
                foreach (array_values($data['requirements']) as $i => $req) {
                    $procedure->requirements()->create([
                        'name' => $req['name'],
                        'description' => $req['description'] ?? null,
                        'is_required' => (bool) ($req['is_required'] ?? true),
                        'file_types' => $req['file_types'] ?? ['.pdf'],
                        'max_size_mb' => (int) ($req['max_size_mb'] ?? 5),
                        'sort' => $i,
                    ]);
                }
            }

            if (array_key_exists('steps', $data)) {
                $procedure->steps()->delete();
                foreach (array_values($data['steps']) as $i => $step) {
                    $procedure->steps()->create([
                        'step_number' => $i + 1,
                        'name' => $step['name'],
                        'description' => $step['description'] ?? null,
                        'area' => $step['area'],
                        'action_required' => $step['action_required'] ?? 'REVISAR_REQUISITOS',
                        'document_requirement' => $step['document_requirement'] ?? null,
                        'can_observe' => (bool) ($step['can_observe'] ?? true),
                        'can_reject' => (bool) ($step['can_reject'] ?? true),
                    ]);
                }
            }

            return $procedure->load(['requirements', 'steps', 'programs', 'faculties']);
        });
    }

    /** Clona un trámite general para crear una versión propia de una carrera. */
    public function bifurcate(Procedure $base, Program $program): Procedure
    {
        $base->load(['requirements', 'steps']);

        $code = $base->code.'-'.$program->code;
        while (Procedure::where('code', $code)->exists()) {
            $code = $base->code.'-'.$program->code.'-'.random_int(10, 99);
        }

        return $this->save([
            'code' => $code,
            'name' => $base->name,
            'description' => $base->description,
            'category' => $base->category,
            'estimated_days' => $base->estimated_days,
            'cost' => $base->cost,
            'requires_payment' => $base->requires_payment,
            'payment_concept' => $base->payment_concept,
            'target_scope' => 'PROGRAMA',
            'program_ids' => [$program->id],
            'parent_id' => $base->id,
            'requirements' => $base->requirements->map->only(['name', 'description', 'is_required', 'file_types', 'max_size_mb'])->all(),
            'steps' => $base->steps->map->only(['name', 'description', 'area', 'action_required', 'document_requirement', 'can_observe', 'can_reject'])->all(),
        ]);
    }
}
