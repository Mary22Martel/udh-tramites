<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\Program;
use App\Models\Submission;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\Presenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(protected WorkflowService $wf) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        return match (true) {
            $user->isAdmin() => $this->admin($request),
            $user->isStudent() => $this->student($user),
            default => $this->staff($user),
        };
    }

    protected function statusCounts($q): array
    {
        $counts = (clone $q)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return collect(Submission::STATUSES)->mapWithKeys(fn ($l, $k) => [$k => (int) ($counts[$k] ?? 0)])->all();
    }

    protected function months(int $n = 6): array
    {
        Carbon::setLocale('es');

        return collect(range($n - 1, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i))->all();
    }

    protected function admin(Request $request)
    {
        $base = Submission::query()
            ->when($request->filled('faculty_id'), fn ($q) => $q->where('faculty_id', $request->faculty_id))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->program_id));

        $status = $this->statusCounts($base);
        $total = array_sum($status);

        $finished = (clone $base)->where('status', 'FINALIZADO')->get(['created_at', 'finished_at']);
        $avgDays = $finished->isEmpty() ? 0 : round($finished->avg(fn ($s) => $s->created_at->diffInHours($s->finished_at) / 24), 1);

        $months = $this->months();
        $rows = (clone $base)->where('created_at', '>=', $months[0])->get(['created_at', 'status', 'finished_at']);
        $byMonth = collect($months)->map(fn ($m) => [
            'label' => ucfirst($m->translatedFormat('M')),
            'registradas' => $rows->filter(fn ($r) => $r->created_at->isSameMonth($m))->count(),
            'finalizadas' => $rows->filter(fn ($r) => $r->finished_at && $r->status === 'FINALIZADO' && $r->finished_at->isSameMonth($m))->count(),
        ]);

        $byProgram = Program::withCount([
            'submissions as total' => fn ($q) => $q->when($request->filled('faculty_id'), fn ($w) => $w->where('faculty_id', $request->faculty_id)),
            'submissions as en_proceso' => fn ($q) => $q->where('status', 'EN_PROCESO'),
            'submissions as observado' => fn ($q) => $q->where('status', 'OBSERVADO'),
            'submissions as finalizado' => fn ($q) => $q->where('status', 'FINALIZADO'),
            'submissions as rechazado' => fn ($q) => $q->where('status', 'RECHAZADO'),
        ])->orderByDesc('total')->get(['id', 'code', 'name'])->filter(fn ($p) => $p->total > 0)->values();

        $topProcedures = (clone $base)->selectRaw('procedure_name as name, procedure_category as category, count(*) as total')
            ->groupBy('procedure_name', 'procedure_category')->orderByDesc('total')->limit(8)->get();

        $byArea = (clone $base)->where('status', 'EN_PROCESO')->selectRaw('current_area, count(*) as total')
            ->groupBy('current_area')->orderByDesc('total')->get()
            ->map(fn ($r) => ['area' => $r->current_area, 'label' => $this->wf->areaLabel($r->current_area), 'total' => (int) $r->total]);

        $revenue = (float) \App\Models\Payment::where('status', 'PAGADO')
            ->whereHas('submission', fn ($q) => $q
                ->when($request->filled('faculty_id'), fn ($w) => $w->where('faculty_id', $request->faculty_id))
                ->when($request->filled('program_id'), fn ($w) => $w->where('program_id', $request->program_id)))
            ->sum('amount');

        // Fuera de plazo: el área actual superó sus días hábiles para atender
        $overdue = (clone $base)->where('status', 'EN_PROCESO')->whereNotNull('step_due_at')->where('step_due_at', '<', now())->count();

        $recent = (clone $base)->with(['student:id,name,student_code', 'program:id,code,name', 'faculty:id,code,name', 'procedure.steps', 'channel:id,name'])
            ->orderByDesc('updated_at')->limit(8)->get()->map(fn ($s) => Presenter::submissionRow($s));

        return [
            'kpis' => [
                'total' => $total,
                'en_proceso' => $status['EN_PROCESO'],
                'observado' => $status['OBSERVADO'],
                'finalizado' => $status['FINALIZADO'],
                'rechazado' => $status['RECHAZADO'],
                'avg_days' => $avgDays,
                'revenue' => $revenue,
                'overdue' => $overdue,
                'procedures' => Procedure::where('is_active', true)->count(),
                'students' => User::where('role', 'ESTUDIANTE')->count(),
                'staff' => User::whereNotIn('role', ['ESTUDIANTE'])->count(),
                'programs' => Program::count(),
            ],
            'by_status' => $status,
            'by_month' => $byMonth,
            'by_program' => $byProgram,
            'top_procedures' => $topProcedures,
            'by_area' => $byArea,
            'recent' => $recent,
        ];
    }

    protected function staff(User $user)
    {
        $pending = $this->wf->tasksQuery($user);
        $attended = Submission::whereHas('events', fn ($e) => $e->where('actor_id', $user->id));
        $myEvents = \App\Models\SubmissionEvent::where('actor_id', $user->id);

        $oldest = (clone $pending)->with(['student:id,name,student_code', 'program:id,code,name', 'faculty:id,code,name', 'procedure.steps', 'channel:id,name'])
            ->orderByRaw('CASE WHEN step_due_at IS NULL THEN 1 ELSE 0 END')->orderBy('step_due_at')->limit(6)->get()->map(fn ($s) => Presenter::submissionRow($s, $user));

        $byProcedure = (clone $pending)->selectRaw('procedure_name as name, count(*) as total')->groupBy('procedure_name')->orderByDesc('total')->limit(6)->get();

        $months = $this->months();
        $events = (clone $myEvents)->where('created_at', '>=', $months[0])->get(['created_at', 'action']);

        return [
            'kpis' => [
                'pending' => (clone $pending)->count(),
                'attended' => (clone $attended)->count(),
                'this_month' => (clone $myEvents)->where('created_at', '>=', now()->startOfMonth())->count(),
                'observed' => (clone $myEvents)->where('action', 'OBSERVADO')->count(),
                // Fuera del plazo que tiene mi área (días hábiles del paso)
                'overdue' => (clone $pending)->whereNotNull('step_due_at')->where('step_due_at', '<', now())->count(),
            ],
            'oldest' => $oldest,
            'by_procedure' => $byProcedure,
            'by_month' => collect($months)->map(fn ($m) => [
                'label' => ucfirst($m->translatedFormat('M')),
                'atendidas' => $events->filter(fn ($e) => $e->created_at->isSameMonth($m))->count(),
            ]),
        ];
    }

    protected function student(User $user)
    {
        $base = Submission::where('student_id', $user->id);

        return [
            'kpis' => $this->statusCounts($base) + ['total' => (clone $base)->count()],
            'pending_payments' => \App\Models\Payment::where('user_id', $user->id)->where('status', 'PENDIENTE')->get(['id', 'code', 'amount', 'concept'])->toArray(),
            'active' => (clone $base)->whereIn('status', ['EN_PROCESO', 'OBSERVADO'])
                ->with(['student:id,name,student_code', 'program:id,code,name', 'faculty:id,code,name', 'procedure.steps', 'channel:id,name'])
                ->orderByDesc('updated_at')->get()->map(fn ($s) => Presenter::submissionRow($s, $user)),
            'available_procedures' => $this->wf->availableProceduresFor($user)->count(),
        ];
    }
}
