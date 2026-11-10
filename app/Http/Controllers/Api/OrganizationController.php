<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\User;
use App\Services\ImportService;
use App\Support\Presenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Facultades, carreras, áreas/ventanillas, personal y estudiantes. */
class OrganizationController extends Controller
{
    // ---------------- Facultades ----------------
    public function faculties()
    {
        return ['data' => Faculty::withCount('programs')->with('programs:id,faculty_id,code,name,director_name')->orderBy('name')->get()];
    }

    public function saveFaculty(Request $request, ?Faculty $faculty = null)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('faculties', 'code')->ignore($faculty?->id)],
            'name' => ['required', 'string', 'max:255'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'dean_email' => ['nullable', 'email'],
        ]);
        $faculty ? $faculty->update($data) : $faculty = Faculty::create($data);

        return ['data' => $faculty];
    }

    public function deleteFaculty(Faculty $faculty)
    {
        abort_if($faculty->programs()->exists(), 422, 'La facultad tiene carreras registradas. Elimínelas o muévalas primero.');
        $faculty->delete();

        return ['ok' => true];
    }

    // ---------------- Carreras ----------------
    public function programs()
    {
        return ['data' => Program::with('faculty:id,code,name')->withCount(['students', 'submissions'])->orderBy('name')->get()];
    }

    public function saveProgram(Request $request, ?Program $program = null)
    {
        $data = $request->validate([
            'faculty_id' => ['required', 'exists:faculties,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('programs', 'code')->ignore($program?->id)],
            'name' => ['required', 'string', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'director_email' => ['nullable', 'email'],
        ]);
        $program ? $program->update($data) : $program = Program::create($data);

        return ['data' => $program->load('faculty:id,code,name')];
    }

    public function deleteProgram(Program $program)
    {
        abort_if($program->submissions()->exists(), 422, 'La carrera tiene solicitudes registradas; no se puede eliminar.');
        $program->delete();

        return ['ok' => true];
    }

    // ---------------- Áreas y ventanillas ----------------
    public function areas()
    {
        return ['data' => Area::with(['responsible:id,name,email', 'channels.responsible:id,name,email', 'channels.programs:id,code,name'])
            ->withCount('members')->orderByDesc('is_system')->orderBy('name')->get()];
    }

    public function saveArea(Request $request, ?Area $area = null)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('areas', 'code')->ignore($area?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_id' => ['nullable', 'exists:users,id'],
        ], ['code.regex' => 'El código solo admite MAYÚSCULAS, números y guiones.']);

        if ($area?->is_system) {
            unset($data['code']); // el código de Tesorería/Matrícula no se cambia
        }
        $area ? $area->update($data) : $area = Area::create($data);
        if (! empty($data['responsible_id'])) {
            User::whereKey($data['responsible_id'])->update(['area_id' => $area->id]);
        }

        return ['data' => $area];
    }

    public function deleteArea(Area $area)
    {
        abort_if($area->is_system, 422, 'Tesorería y Matrícula son áreas del sistema y no se pueden eliminar.');
        $area->delete();

        return ['ok' => true];
    }

    /** Reemplaza las ventanillas de un área: [{name, responsible_id, program_ids[]}] */
    public function saveChannels(Request $request, Area $area)
    {
        $data = $request->validate([
            'has_channels' => ['boolean'],
            'channels' => ['array'],
            'channels.*.id' => ['nullable', 'integer'],
            'channels.*.name' => ['required', 'string', 'max:255'],
            'channels.*.responsible_id' => ['nullable', 'exists:users,id'],
            'channels.*.program_ids' => ['array'],
            'channels.*.program_ids.*' => ['integer', 'exists:programs,id'],
        ]);

        DB::transaction(function () use ($area, $data) {
            $area->update(['has_channels' => $data['has_channels'] ?? false]);
            $keep = [];
            foreach ($data['channels'] ?? [] as $c) {
                $channel = ! empty($c['id']) ? $area->channels()->find($c['id']) : null;
                $channel ??= $area->channels()->make();
                $channel->fill(['name' => $c['name'], 'responsible_id' => $c['responsible_id'] ?? null])->save();
                $channel->programs()->sync($c['program_ids'] ?? []);
                $keep[] = $channel->id;
                if (! empty($c['responsible_id'])) {
                    User::whereKey($c['responsible_id'])->update(['area_id' => $area->id]);
                }
            }
            $area->channels()->whereNotIn('id', $keep)->delete();

            // Recalcular la ventanilla de las solicitudes que están en esta área
            $wf = app(\App\Services\WorkflowService::class);
            \App\Models\Submission::where('current_area', $area->code)->whereIn('status', ['EN_PROCESO', 'OBSERVADO'])
                ->with('procedure.steps')->get()->each(function ($s) use ($wf) {
                    $wf->resolveAssignment($s);
                    $s->saveQuietly();
                });
        });

        return $this->areas();
    }

    // ---------------- Personal ----------------
    public function staff(Request $request)
    {
        $q = User::whereNot('role', 'ESTUDIANTE')
            ->when($request->filled('role'), fn ($w) => $w->where('role', $request->role))
            ->when($request->filled('search'), fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%")))
            ->orderBy('role')->orderBy('name');

        return ['data' => $q->get()->map(fn ($u) => Presenter::user($u))];
    }

    public function students(Request $request)
    {
        $page = User::where('role', 'ESTUDIANTE')
            ->when($request->filled('program_id'), fn ($w) => $w->where('program_id', $request->program_id))
            ->when($request->filled('search'), fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$request->search}%")->orWhere('student_code', 'like', "%{$request->search}%")))
            ->withCount('submissions')
            ->orderBy('name')->paginate(20);

        return [
            'data' => collect($page->items())->map(fn ($u) => Presenter::user($u) + ['submissions_count' => $u->submissions_count]),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    public function saveUser(Request $request, ?User $user = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'job_title' => ['nullable', 'string', 'max:255'],
            'dni' => ['nullable', 'string', 'max:15'],
            'phone' => ['nullable', 'string', 'max:30'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'student_code' => ['nullable', 'string', 'max:20'],
            'current_semester' => ['nullable', 'string', 'max:30'],
            'academic_state' => ['nullable', Rule::in(['REGULAR', 'EGRESADO', 'GRADUADO'])],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (! empty($data['program_id'])) {
            $data['faculty_id'] = Program::find($data['program_id'])->faculty_id;
        }
        if (! empty($data['area_id'])) {
            $data['department'] = Area::find($data['area_id'])->name;
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user) {
            $user->update($data);
        } else {
            $user = User::create($data + ['password' => ImportService::DEFAULT_PASSWORD]);
        }

        return ['data' => Presenter::user($user->fresh())];
    }

    public function toggleUser(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'No puedes desactivar tu propia cuenta.');
        $user->update(['is_active' => ! $user->is_active]);

        return ['data' => Presenter::user($user)];
    }
}
