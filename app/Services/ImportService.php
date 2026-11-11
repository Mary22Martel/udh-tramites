<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaChannel;
use App\Models\Faculty;
use App\Models\Procedure;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importación masiva desde CSV (se puede editar en Excel).
 * La usan los seeders, el comando `php artisan udh:importar` y la pantalla "Carga de datos".
 */
class ImportService
{
    public const DEFAULT_PASSWORD = 'udh2026';

    public const TYPES = [
        'carreras' => [
            'label' => 'Facultades y carreras',
            'columns' => ['facultad_codigo', 'facultad', 'decano', 'programa_codigo', 'programa', 'nivel', 'modalidades', 'director', 'director_email', 'alias'],
            'example' => ['F-ING', 'Facultad de Ingeniería', 'Bertha Lucila Campos Rios', 'ING-SIST', 'Ingeniería de Sistemas e Informática', 'PREGRADO', 'Presencial', 'Ethel Manzano', 'sistemas@udh.edu.pe', 'INGENIERÍA DE SISTEMAS E INFORMÁTICA'],
        ],
        'personal' => [
            'label' => 'Personal administrativo',
            'columns' => ['nombre', 'email', 'rol', 'cargo', 'area_codigo', 'facultad_codigo', 'programa_codigo', 'ventanilla', 'es_jefe', 'dni', 'telefono'],
            'example' => ['Lic. Marco Antonio Ríos', 'tesoreria@udh.edu.pe', 'TESORERIA', 'Jefe de Tesorería', 'TESORERIA', '', '', '', 'SI', '40123456', '962000111'],
        ],
        'estudiantes' => [
            'label' => 'Estudiantes',
            'columns' => ['codigo', 'nombre', 'email', 'programa_codigo', 'ciclo', 'estado', 'dni'],
            'example' => ['2021104829', 'Juan Pérez Soto', '2021104829@udh.edu.pe', 'ING-CIVIL', 'VIII Ciclo', 'REGULAR', '72345678'],
        ],
        'tramites' => [
            'label' => 'Trámites por carrera (o tu reporte destiptra / desesc)',
            'columns' => ['tramite', 'programa', 'categoria', 'costo', 'dias', 'flujo'],
            'example' => ['CONSTANCIA DE PRÁCTICAS PRE PROFESIONALES', 'ING-SIST', 'Constancias y Certificados', '25', '5', 'PROGRAMA_ACADEMICO>MATRICULA'],
        ],
        'solicitudes_demo' => [
            'label' => 'Solicitudes de demostración por carrera',
            'columns' => ['programa_codigo', 'cantidad'],
            'example' => ['ING-CIVIL', '25'],
        ],
    ];

    public function __construct(protected DemoDataGenerator $demo) {}

    /** Hash de la contraseña por defecto (se calcula una sola vez: acelera importaciones grandes). */
    public static function defaultPasswordHash(): string
    {
        static $hash = null;

        return $hash ??= \Illuminate\Support\Facades\Hash::make(self::DEFAULT_PASSWORD);
    }

    /** @return array{created:int,updated:int,skipped:list<string>,errors:list<string>} */
    public function import(string $type, string $path): array
    {
        $rows = CsvReader::read($path);

        return DB::transaction(fn () => match ($type) {
            'carreras' => $this->carreras($rows),
            'personal' => $this->personal($rows),
            'estudiantes' => $this->estudiantes($rows),
            'tramites' => $this->tramites($rows),
            'solicitudes_demo' => $this->solicitudesDemo($rows),
            default => throw new \InvalidArgumentException("Tipo de importación desconocido: {$type}"),
        });
    }

    public function template(string $type): string
    {
        $def = self::TYPES[$type];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea tildes
        fputcsv($out, $def['columns'], ';', '"', '\\');
        fputcsv($out, $def['example'], ';', '"', '\\');
        rewind($out);

        return stream_get_contents($out);
    }

    protected function result(): array
    {
        return ['created' => 0, 'updated' => 0, 'skipped' => [], 'errors' => []];
    }

    // ------------------------------------------------------------------

    public function carreras(array $rows): array
    {
        $r = $this->result();
        foreach ($rows as $i => $row) {
            $fc = strtoupper($row['facultad_codigo'] ?? '');
            if ($fc === '' || empty($row['facultad'])) {
                $r['errors'][] = 'Fila '.($i + 2).': falta facultad_codigo o facultad';

                continue;
            }
            $faculty = Faculty::updateOrCreate(['code' => $fc], array_filter([
                'name' => $row['facultad'],
                'dean_name' => $row['decano'] ?? null,
            ]));

            $pc = strtoupper($row['programa_codigo'] ?? '');
            if ($pc === '') {
                continue;
            }
            $program = Program::firstOrNew(['code' => $pc]);
            $program->wasRecentlyCreated = ! $program->exists;
            $program->fill(array_filter([
                'faculty_id' => $faculty->id,
                'name' => $row['programa'] ?? $pc,
                'level' => array_key_exists(strtoupper($row['nivel'] ?? ''), Program::LEVELS) ? strtoupper($row['nivel']) : null,
                'modalities' => $row['modalidades'] ?? null,
                'aliases' => $row['alias'] ?? null,
                'director_name' => $row['director'] ?? null,
                'director_email' => $row['director_email'] ?? null,
            ]))->save();
            $program->wasRecentlyCreated ? $r['created']++ : $r['updated']++;
        }

        return $r;
    }

    public function personal(array $rows): array
    {
        $r = $this->result();
        foreach ($rows as $i => $row) {
            $email = Str::lower($row['email'] ?? '');
            $role = strtoupper($row['rol'] ?? '');
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($row['nombre'])) {
                $r['errors'][] = 'Fila '.($i + 2).': nombre o email inválido';

                continue;
            }
            if (! array_key_exists($role, User::ROLES) || $role === 'ESTUDIANTE') {
                $r['errors'][] = 'Fila '.($i + 2).": rol \"{$role}\" no válido (use ADMINISTRADOR, TESORERIA, MATRICULA, PROGRAMA_ACADEMICO, FACULTAD o AREA_CENTRAL)";

                continue;
            }

            $data = [
                'name' => $row['nombre'],
                'role' => $role,
                'job_title' => $row['cargo'] ?? null,
                'dni' => $row['dni'] ?? null,
                'phone' => $row['telefono'] ?? null,
                'is_active' => true,
            ];

            if (! empty($row['area_codigo']) || in_array($role, ['TESORERIA', 'MATRICULA'], true)) {
                $area = Area::where('code', strtoupper($row['area_codigo'] ?: $role))->first();
                if (! $area) {
                    $r['errors'][] = 'Fila '.($i + 2).": área \"{$row['area_codigo']}\" no existe";

                    continue;
                }
                $data['area_id'] = $area->id;
                $data['department'] = $area->name;
            }
            if (! empty($row['facultad_codigo'])) {
                $data['faculty_id'] = Faculty::where('code', strtoupper($row['facultad_codigo']))->value('id');
            }
            if (! empty($row['programa_codigo'])) {
                $program = Program::where('code', strtoupper($row['programa_codigo']))->first();
                $data['program_id'] = $program?->id;
                $data['faculty_id'] = $program?->faculty_id ?? ($data['faculty_id'] ?? null);
            }

            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update($data);
                $r['updated']++;
            } else {
                $user = User::create($data + ['email' => $email, 'password' => self::defaultPasswordHash()]);
                $r['created']++;
            }

            if (isset($area) && in_array(Str::upper($row['es_jefe'] ?? ''), ['SI', 'SÍ', 'S', '1', 'X'], true)) {
                $area->update(['responsible_id' => $user->id]);
            }
            if (isset($area) && ! empty($row['ventanilla'])) {
                $channel = AreaChannel::firstOrCreate(['area_id' => $area->id, 'name' => $row['ventanilla']]);
                $channel->update(['responsible_id' => $user->id]);
                $area->update(['has_channels' => true]);
            }
            unset($area);
        }

        return $r;
    }

    public function estudiantes(array $rows): array
    {
        $r = $this->result();
        foreach ($rows as $i => $row) {
            $code = $row['codigo'] ?? '';
            $program = Program::where('code', strtoupper($row['programa_codigo'] ?? ''))->first();
            if ($code === '' || empty($row['nombre']) || ! $program) {
                $r['errors'][] = 'Fila '.($i + 2).': falta código, nombre o programa_codigo válido';

                continue;
            }
            $email = Str::lower($row['email'] ?: "{$code}@udh.edu.pe");
            $data = [
                'name' => $row['nombre'],
                'role' => 'ESTUDIANTE',
                'student_code' => $code,
                'program_id' => $program->id,
                'faculty_id' => $program->faculty_id,
                'current_semester' => $row['ciclo'] ?? null,
                'academic_state' => strtoupper($row['estado'] ?? '') ?: 'REGULAR',
                'dni' => $row['dni'] ?? null,
            ];
            $user = User::where('email', $email)->orWhere('student_code', $code)->first();
            if ($user) {
                $user->update($data);
                $r['updated']++;
            } else {
                User::create($data + ['email' => $email, 'password' => self::defaultPasswordHash()]);
                $r['created']++;
            }
        }

        return $r;
    }

    /**
     * Importa trámites por carrera. Acepta:
     *  - la plantilla (tramite; programa; categoria; costo; dias; flujo), o
     *  - el reporte del sistema académico tal cual (destiptra; desesc).
     * Las filas se agrupan por nombre de trámite: un trámite queda vinculado a todas sus carreras.
     * "programa" puede ser el código (ING-CIVIL), el nombre o cualquier alias de la carrera.
     */
    public function tramites(array $rows): array
    {
        $r = $this->result();
        $builder = app(ProcedureBuilder::class);
        $resolve = $this->programResolver();

        $groups = [];
        foreach ($rows as $i => $row) {
            $name = trim(preg_replace('/\s+/', ' ', $row['tramite'] ?? $row['destiptra'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ref = trim($row['programa'] ?? $row['programa_codigo'] ?? $row['carrera'] ?? $row['desesc'] ?? '');
            $key = WorkflowService::normalize($name);
            $groups[$key] ??= ['name' => $name, 'row' => $row, 'programs' => [], 'general' => false];

            if ($ref === '' || strtoupper($ref) === 'TODOS') {
                $groups[$key]['general'] = true;

                continue;
            }
            $pid = $resolve($ref);
            if (! $pid) {
                $r['errors'][] = 'Fila '.($i + 2).": carrera \"{$ref}\" no reconocida (agrégala como alias en Facultades y carreras)";

                continue;
            }
            $groups[$key]['programs'][$pid] = true;
        }

        $existing = Procedure::with('programs:id')->get()->keyBy(fn ($p) => WorkflowService::normalize($p->name));

        foreach ($groups as $key => $g) {
            $programIds = array_keys($g['programs']);
            if (! $programIds && ! $g['general']) {
                continue;
            }
            if ($proc = $existing->get($key)) {
                if ($proc->target_scope === 'PROGRAMA' && $programIds) {
                    $added = array_diff($programIds, $proc->programs->pluck('id')->all());
                    if ($added) {
                        $proc->programs()->syncWithoutDetaching($added);
                        $r['updated']++;

                        continue;
                    }
                }
                $r['skipped'][] = $g['name'];

                continue;
            }

            $row = $g['row'];
            $cost = str_replace(',', '.', $row['costo'] ?? '');
            $flow = array_filter(array_map('trim', preg_split('/[>,|]/', $row['flujo'] ?? '')));
            $builder->createDefault($g['name'], [
                'program_ids' => $g['general'] ? [] : $programIds,
                'category' => $row['categoria'] ?? null,
                'cost' => is_numeric($cost) ? (float) $cost : 20.0,
                'days' => (int) ($row['dias'] ?? 0) ?: 5,
                'flow' => array_map('strtoupper', $flow),
            ]);
            $r['created']++;
        }

        return $r;
    }

    /** Devuelve una función que encuentra el id de una carrera por código, nombre o alias. */
    public function programResolver(): \Closure
    {
        $clean = fn ($s) => trim(preg_replace('/\s+/', ' ', WorkflowService::normalize((string) $s)));
        $map = [];
        foreach (Program::all() as $p) {
            $map[$clean($p->code)] = $p->id;
            $map[$clean($p->name)] = $p->id;
            foreach (explode('|', (string) $p->aliases) as $alias) {
                if (trim($alias) !== '') {
                    $map[$clean($alias)] = $p->id;
                }
            }
        }

        return function (string $ref) use ($map, $clean) {
            $k = $clean($ref);
            if (isset($map[$k])) {
                return $map[$k];
            }
            // Quitar la modalidad: "PSICOLOGIA - SEMIPRESENCIAL" → "psicologia"
            $base = trim(preg_replace('/\s*-\s*(semipresencial|distancia)$|\.$/', '', $k));

            return $map[$base] ?? null;
        };
    }

    public function solicitudesDemo(array $rows): array
    {
        $r = $this->result();
        $counts = [];
        foreach ($rows as $i => $row) {
            $program = Program::where('code', strtoupper($row['programa_codigo'] ?? ''))->first();
            $n = (int) ($row['cantidad'] ?? 0);
            if (! $program || $n <= 0) {
                $r['errors'][] = 'Fila '.($i + 2).': programa_codigo o cantidad inválidos';

                continue;
            }
            $counts[$program->id] = ($counts[$program->id] ?? 0) + min($n, 500);
        }
        $r['created'] = $this->demo->generateBatch($counts);

        return $r;
    }
}
