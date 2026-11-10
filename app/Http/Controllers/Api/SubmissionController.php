<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\Submission;
use App\Models\SubmissionDocument;
use App\Services\DocumentRenderer;
use App\Services\WorkflowService;
use App\Support\Presenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    protected const WITH = ['student:id,name,student_code,email', 'program:id,code,name', 'faculty:id,code,name', 'procedure.steps', 'channel:id,name', 'payments'];

    public function __construct(protected WorkflowService $wf) {}

    /**
     * scope = mias (estudiante) | pendientes (bandeja del personal) | atendidos | todas (admin)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $scope = $request->input('scope', $user->isStudent() ? 'mias' : ($user->isAdmin() ? 'todas' : 'pendientes'));

        $q = match (true) {
            $user->isStudent() => Submission::where('student_id', $user->id),
            $scope === 'pendientes' => $this->wf->tasksQuery($user),
            $scope === 'atendidos' => Submission::whereHas('events', fn ($e) => $e->where('actor_id', $user->id)),
            $user->isAdmin() => Submission::query(),
            default => abort(403),
        };

        $this->applyFilters($q, $request);
        $page = $q->with(self::WITH)->orderByDesc('updated_at')->paginate((int) $request->input('per_page', 15));

        return [
            'data' => collect($page->items())->map(fn ($s) => Presenter::submissionRow($s, $user)),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    protected function applyFilters(Builder $q, Request $r): void
    {
        $q->when($r->filled('status') && $r->status !== 'TODOS', fn ($w) => $w->where('status', $r->status))
            ->when($r->filled('program_id'), fn ($w) => $w->where('program_id', $r->program_id))
            ->when($r->filled('faculty_id'), fn ($w) => $w->where('faculty_id', $r->faculty_id))
            ->when($r->filled('procedure_id'), fn ($w) => $w->where('procedure_id', $r->procedure_id))
            ->when($r->filled('area'), fn ($w) => $w->where('current_area', $r->area))
            ->when($r->filled('from'), fn ($w) => $w->whereDate('created_at', '>=', $r->from))
            ->when($r->filled('to'), fn ($w) => $w->whereDate('created_at', '<=', $r->to))
            ->when($r->filled('search'), function ($w) use ($r) {
                $s = '%'.$r->search.'%';
                $w->where(fn ($x) => $x->where('code', 'like', $s)
                    ->orWhere('procedure_name', 'like', $s)
                    ->orWhereHas('student', fn ($st) => $st->where('name', 'like', $s)->orWhere('student_code', 'like', $s)));
            });
    }

    public function show(Request $request, Submission $submission)
    {
        abort_unless($this->wf->canView($request->user(), $submission), 403);

        return ['data' => Presenter::submissionDetail($submission->load(self::WITH), $request->user())];
    }

    /** El estudiante presenta una nueva solicitud (con archivos reales). */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403, 'Solo los estudiantes pueden iniciar trámites.');

        $data = $request->validate([
            'procedure_id' => ['required', 'exists:procedures,id'],
            'description' => ['required', 'string', 'min:15', 'max:2000'],
            'files' => ['array'],
            'files.*' => ['file', 'max:51200'],
        ], [
            'description.min' => 'Escribe una justificación de al menos 15 caracteres.',
        ]);

        $procedure = Procedure::with(['requirements', 'steps'])->findOrFail($data['procedure_id']);
        if (! $this->wf->availableProceduresFor($user)->contains('id', $procedure->id)) {
            throw ValidationException::withMessages(['procedure_id' => 'Este trámite no está disponible para tu carrera.']);
        }

        // Validar formato y tamaño de cada requisito
        $files = [];
        foreach ($procedure->requirements as $req) {
            $file = $request->file("files.{$req->id}");
            if (! $file) {
                continue;
            }
            $ext = '.'.strtolower($file->getClientOriginalExtension());
            $allowed = array_map('strtolower', $req->file_types ?? []);
            if ($allowed && ! in_array($ext, $allowed, true) && ! ($ext === '.jpeg' && in_array('.jpg', $allowed, true))) {
                throw ValidationException::withMessages(["files.{$req->id}" => "{$req->name}: formato no permitido (".implode(', ', $allowed).').']);
            }
            if ($file->getSize() > $req->max_size_mb * 1024 * 1024) {
                throw ValidationException::withMessages(["files.{$req->id}" => "{$req->name}: supera {$req->max_size_mb} MB."]);
            }
            $files[$req->id] = $file;
        }

        // Si el trámite tiene costo, la solicitud queda "Pendiente de pago" y se genera su ticket en "Mis pagos"
        $s = $this->wf->start($procedure, $user, $data['description'], $files);

        return ['data' => Presenter::submissionDetail($s->load(self::WITH), $user)];
    }

    public function approve(Request $request, Submission $submission)
    {
        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip', 'max:20480'],
        ], ['file.mimes' => 'Formatos permitidos: PDF, imagen, Word, Excel o ZIP.']);
        $s = $this->wf->approve($submission, $request->user(), $data['comment'] ?? null, $request->file('file'));

        return ['data' => Presenter::submissionDetail($s->load(self::WITH), $request->user())];
    }

    public function observe(Request $request, Submission $submission)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'detail' => ['required', 'string', 'max:2000'],
        ]);
        $s = $this->wf->observe($submission, $request->user(), $data['reason'], $data['detail']);

        return ['data' => Presenter::submissionDetail($s->load(self::WITH), $request->user())];
    }

    public function reject(Request $request, Submission $submission)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $s = $this->wf->reject($submission, $request->user(), $data['reason']);

        return ['data' => Presenter::submissionDetail($s->load(self::WITH), $request->user())];
    }

    public function correct(Request $request, Submission $submission)
    {
        $data = $request->validate([
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ]);
        $s = $this->wf->correct($submission, $request->user(), $data['comment'], $request->file('file'));

        return ['data' => Presenter::submissionDetail($s->load(self::WITH), $request->user())];
    }

    /** Descarga un documento: el archivo real o el PDF generado por el sistema. */
    public function download(Request $request, SubmissionDocument $document, DocumentRenderer $renderer)
    {
        abort_unless($this->wf->canView($request->user(), $document->submission), 403);
        $inline = $request->boolean('inline');

        if ($document->path && Storage::disk('local')->exists($document->path)) {
            return $inline
                ? Storage::disk('local')->response($document->path, $document->name)
                : Storage::disk('local')->download($document->path, $document->name);
        }

        $name = preg_replace('/\.(jpe?g|png|docx?)$/i', '.pdf', $document->name);

        return response($renderer->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
        ]);
    }

    /** Exportar a CSV (se abre en Excel). */
    public function export(Request $request)
    {
        $q = Submission::query()->with(['student', 'program', 'faculty', 'procedure.steps']);
        $this->applyFilters($q, $request);

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Expediente', 'Trámite', 'Categoría', 'Estudiante', 'Código', 'Carrera', 'Facultad', 'Estado', 'Etapa actual', 'Costo', 'Pago', 'Fecha registro', 'Fecha fin'], ';', '"', '\\');
        $q->orderByDesc('created_at')->chunk(500, function ($rows) use ($out) {
            foreach ($rows as $s) {
                $step = $this->wf->currentStep($s);
                fputcsv($out, [
                    $s->code, $s->procedure_name, $s->procedure_category, $s->student?->name, $s->student?->student_code,
                    $s->program?->name, $s->faculty?->name, Submission::STATUSES[$s->status] ?? $s->status,
                    in_array($s->status, ['EN_PROCESO', 'OBSERVADO']) ? $step?->name : '-',
                    number_format($s->cost, 2, '.', ''), $s->payment_status,
                    $s->created_at?->format('d/m/Y H:i'), $s->finished_at?->format('d/m/Y H:i'),
                ], ';', '"', '\\');
            }
        });
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="solicitudes_'.now()->format('Ymd_His').'.csv"',
        ]);
    }
}
