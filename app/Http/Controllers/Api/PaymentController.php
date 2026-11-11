<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CajaService;
use App\Services\CulqiGateway;
use App\Services\WorkflowService;
use App\Support\Presenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Módulo de pagos.
 *  Estudiante: ve sus tickets ("Mis pagos") y los paga con Culqi o con el simulador.
 *  Tesorería / Administrador: ven todos los pagos que llegan (no los aprueban: los confirma la pasarela).
 */
class PaymentController extends Controller
{
    public function __construct(protected WorkflowService $wf) {}

    /** Datos que el navegador necesita para abrir el checkout (la llave pública sí se puede exponer). */
    public function config()
    {
        $driver = config('services.payments.driver') === 'culqi' && config('services.culqi.public_key') ? 'culqi' : 'simulado';

        return [
            'driver' => $driver,
            'public_key' => $driver === 'culqi' ? config('services.culqi.public_key') : null,
            'orders' => $driver === 'culqi' && config('services.culqi.orders'),
            'methods' => Payment::METHODS,
            'caja_simulated' => (bool) config('services.caja.simulate'),
            'days_to_pay' => Payment::DAYS_TO_PAY,
        ];
    }

    // ------------------------------------------------------------------ Estudiante

    public function mine(Request $request, CajaService $caja)
    {
        $caja->sync($request->user()); // si ya pagó en caja, su ticket pasa a "Pagados"

        $items = Payment::with('submission:id,code,procedure_name,procedure_category,status,program_id')
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['PENDIENTE', 'PAGADO'])
            ->orderByRaw("CASE status WHEN 'PENDIENTE' THEN 0 ELSE 1 END")->latest('id')->get();

        return [
            'data' => $items->map(fn ($p) => $p->toPublic(true)),
            'pending_total' => (float) $items->where('status', 'PENDIENTE')->sum('amount'),
        ];
    }

    /** Pago con el simulador (modo demo, sin cuentas reales). */
    public function simulate(Request $request, Payment $payment)
    {
        $this->guardPayable($request, $payment);

        $data = $request->validate([
            'method' => ['required', 'in:YAPE,PLIN,TARJETA'],
            'phone' => ['required_if:method,YAPE,PLIN', 'nullable', 'regex:/^9\d{8}$/'],
            'otp' => ['required_if:method,YAPE,PLIN', 'nullable', 'digits:6'],
            'card_number' => ['required_if:method,TARJETA', 'nullable', 'string'],
            'card_exp' => ['required_if:method,TARJETA', 'nullable', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'card_cvv' => ['required_if:method,TARJETA', 'nullable', 'digits_between:3,4'],
            'card_name' => ['required_if:method,TARJETA', 'nullable', 'string', 'max:80'],
        ], [
            'phone.regex' => 'El celular debe tener 9 dígitos y empezar con 9.',
            'phone.required_if' => 'Ingresa tu número de celular.',
            'otp.required_if' => 'Ingresa el código de aprobación.',
            'otp.digits' => 'El código de aprobación tiene 6 dígitos.',
            'card_number.required_if' => 'Ingresa el número de tarjeta.',
            'card_exp.required_if' => 'Ingresa la fecha de vencimiento.',
            'card_exp.regex' => 'Formato MM/AA.',
            'card_cvv.required_if' => 'Ingresa el CVV.',
            'card_cvv.digits_between' => 'El CVV tiene 3 o 4 dígitos.',
            'card_name.required_if' => 'Ingresa el nombre del titular.',
        ]);

        $payment->increment('attempts');
        usleep(900_000); // simula la espera de la pasarela

        if ($data['method'] === 'TARJETA') {
            $num = preg_replace('/\D/', '', $data['card_number']);
            if (strlen($num) < 15 || ! $this->luhn($num)) {
                throw ValidationException::withMessages(['card_number' => 'El número de tarjeta no es válido.']);
            }
            [$mm, $yy] = explode('/', $data['card_exp']);
            if (now()->startOfMonth()->gt(now()->setDate(2000 + (int) $yy, (int) $mm, 1)->startOfMonth())) {
                throw ValidationException::withMessages(['card_exp' => 'La tarjeta está vencida.']);
            }
            $reference = '•••• '.substr($num, -4);
            $failure = str_ends_with($num, '0002') ? 'La tarjeta no tiene fondos suficientes.' : null;
        } else {
            $reference = substr($data['phone'], 0, 1).'•• ••• '.substr($data['phone'], -3);
            $failure = $data['otp'] === '000000' ? 'El código de aprobación es inválido o ya expiró.' : null;
        }

        if ($failure) {
            $payment->update(['failure_reason' => $failure]);
            throw ValidationException::withMessages(['payment' => "Pago rechazado: {$failure}"]);
        }

        $s = $this->wf->markPaid($payment, $data['method'], $reference, $this->txCode(), 'SIMULADO');

        return $this->paidResponse($payment, $s);
    }

    /** "Ya pagué en caja": consulta la caja y, si encuentra el pago, el trámite pasa a "en proceso". */
    public function cajaCheck(Request $request, Payment $payment, CajaService $caja)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        $caja->sync($request->user());
        $payment->refresh();
        if ($payment->status === 'PAGADO') {
            return $this->paidResponse($payment, $payment->submission);
        }

        return ['paid' => false, 'message' => 'Todavía no encontramos tu pago en Caja UDH. Si acabas de pagar, espera unos minutos y vuelve a verificar.'];
    }

    /** SOLO DEMO: simula que el estudiante pagó su ticket en la Caja UDH. */
    public function cajaSimulate(Request $request, Payment $payment, CajaService $caja)
    {
        abort_unless(config('services.caja.simulate'), 404);
        $this->guardPayable($request, $payment);
        usleep(600_000);
        $caja->simulate($payment);
        $caja->sync($request->user());

        return $this->paidResponse($payment, $payment->fresh()->submission);
    }

    /** Tarjeta / Yape con Culqi: el navegador manda el token y aquí se crea el cargo. */
    public function culqiCharge(Request $request, Payment $payment, CulqiGateway $culqi)
    {
        $this->guardPayable($request, $payment);
        $data = $request->validate(['token' => ['required', 'string'], 'method' => ['nullable', 'string']]);
        $payment->increment('attempts');

        $r = $culqi->charge($payment, $data['token'], $request->user()->email, $data['method'] ?? null);
        if (! $r['ok']) {
            $payment->update(['failure_reason' => $r['message']]);
            throw ValidationException::withMessages(['payment' => 'Pago rechazado: '.$r['message']]);
        }

        $s = $this->wf->markPaid($payment, $r['method'], $r['reference'], $r['charge_id'] ?? $this->txCode(), 'CULQI', $r['charge_id']);

        return $this->paidResponse($payment, $s);
    }

    /** Billeteras / banca móvil / agentes: crea la orden en Culqi antes de abrir el checkout. */
    public function culqiOrder(Request $request, Payment $payment, CulqiGateway $culqi)
    {
        $this->guardPayable($request, $payment);
        try {
            return ['order_id' => $culqi->createOrder($payment)];
        } catch (\Throwable $e) {
            Log::warning('Culqi orden: '.$e->getMessage());

            // Sin orden el checkout igual permite tarjeta y Yape
            return ['order_id' => null, 'message' => $e->getMessage()];
        }
    }

    /** El estudiante pagó una orden (Plin, banca móvil, agente): se verifica en Culqi. */
    public function culqiCheck(Request $request, Payment $payment, CulqiGateway $culqi)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);
        if ($payment->status === 'PAGADO') {
            return $this->paidResponse($payment, $payment->submission);
        }
        if (! $payment->gateway_order_id || ! $culqi->orderIsPaid($payment->gateway_order_id)) {
            return ['paid' => false, 'message' => 'Aún no recibimos la confirmación de Culqi. Si ya pagaste, espera unos minutos y vuelve a verificar.'];
        }
        $s = $this->wf->markPaid($payment, 'BILLETERA', null, $payment->gateway_order_id, 'CULQI');

        return $this->paidResponse($payment, $s);
    }

    /** Webhook de Culqi (evento order.status.changed). Siempre se vuelve a consultar la orden a Culqi. */
    public function culqiWebhook(Request $request, CulqiGateway $culqi)
    {
        $data = $request->input('data');
        $data = is_string($data) ? json_decode($data, true) : $data;
        $orderId = $data['id'] ?? null;

        if ($orderId && ($payment = Payment::where('gateway_order_id', $orderId)->first())) {
            try {
                if ($payment->status !== 'PAGADO' && $culqi->orderIsPaid($orderId)) {
                    $this->wf->markPaid($payment, 'BILLETERA', null, $orderId, 'CULQI');
                }
            } catch (\Throwable $e) {
                Log::error('Webhook Culqi: '.$e->getMessage());
            }
        }

        return response()->json(['received' => true]);
    }

    /** Comprobante de pago en PDF (estudiante dueño, Tesorería o Administrador). */
    public function receipt(Request $request, Payment $payment, \App\Services\ReceiptRenderer $renderer)
    {
        $this->guardViewer($request, $payment);
        abort_unless($payment->status === 'PAGADO', 422, 'El ticket aún no está pagado.');

        return response($renderer->render($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="comprobante_'.$payment->code.'.pdf"',
        ]);
    }

    /** Detalle de un pago: lo importante del pago, del estudiante y del trámite. */
    public function show(Request $request, Payment $payment)
    {
        $this->guardViewer($request, $payment);
        $payment->load(['user', 'submission.program', 'submission.faculty', 'submission.procedure.steps']);
        $s = $payment->submission;
        $u = $payment->user;
        $steps = $s?->procedure?->steps->values() ?? collect();

        return ['data' => $payment->toPublic(true) + [
            'gateway_charge_id' => $payment->gateway_charge_id,
            'attempts' => $payment->attempts,
            'student_full' => [
                'name' => $u->name, 'student_code' => $u->student_code, 'email' => $u->email, 'dni' => $u->dni, 'phone' => $u->phone,
                'program' => $s?->program?->name, 'faculty' => $s?->faculty?->name, 'semester' => $u->current_semester,
            ],
            'tramite' => $s ? [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->procedure_name,
                'status' => $s->status,
                'created_at' => $s->created_at?->toIso8601String(),
                'steps' => $steps->map(fn ($st, $i) => [
                    'label' => $this->wf->areaLabel($st->area, $s),
                    'state' => match (true) {
                        $s->status === 'FINALIZADO' || $i < $s->current_step_index => 'done',
                        $s->status === 'PENDIENTE_PAGO' => $i === 0 ? 'done' : 'pending',
                        $i === $s->current_step_index && in_array($s->status, ['EN_PROCESO', 'OBSERVADO'], true) => 'current',
                        $i === $s->current_step_index && $s->status === 'RECHAZADO' => 'rejected',
                        default => 'pending',
                    },
                ])->values(),
            ] : null,
        ]];
    }

    // ------------------------------------------------------------------ Tesorería / Administrador

    public function index(Request $request)
    {
        $this->guardTreasury($request);
        $q = $this->filtered($request);

        $paidBase = Payment::where('status', 'PAGADO');
        $kpis = [
            'total' => (float) (clone $paidBase)->sum('amount'),
            'count' => (clone $paidBase)->count(),
            'today' => (float) (clone $paidBase)->whereDate('paid_at', today())->sum('amount'),
            'month' => (float) (clone $paidBase)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'pending_count' => Payment::where('status', 'PENDIENTE')->count(),
            'pending_total' => (float) Payment::where('status', 'PENDIENTE')->sum('amount'),
            'filtered_total' => (float) (clone $q)->where('status', 'PAGADO')->sum('amount'),
        ];
        $byMethod = (clone $paidBase)->selectRaw('method, count(*) as n, sum(amount) as total')->groupBy('method')->get()
            ->map(fn ($r) => ['method' => $r->method, 'label' => Payment::METHODS[$r->method] ?? $r->method, 'n' => (int) $r->n, 'total' => (float) $r->total]);

        $page = $q->with(['user:id,name,student_code,email', 'submission:id,code,procedure_name,procedure_category,status,program_id', 'submission.program:id,code,name'])
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')->paginate(20);

        return [
            'kpis' => $kpis,
            'by_method' => $byMethod,
            'data' => collect($page->items())->map(fn ($p) => $p->toPublic(true)),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    public function export(Request $request)
    {
        $this->guardTreasury($request);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Ticket', 'Estado', 'Fecha de pago', 'Estudiante', 'Código', 'Carrera', 'Expediente', 'Trámite', 'Medio', 'Referencia', 'Transacción', 'Pasarela', 'Monto (S/)'], ';', '"', '\\');
        $this->filtered($request)->with(['user', 'submission.program'])->orderBy('id')->chunk(500, function ($rows) use ($out) {
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->code, Payment::STATUSES[$p->status] ?? $p->status, $p->paid_at?->format('d/m/Y H:i'), $p->user?->name, $p->user?->student_code,
                    $p->submission?->program?->name, $p->submission?->code, $p->submission?->procedure_name,
                    Payment::METHODS[$p->method] ?? $p->method, $p->reference, $p->transaction_code, $p->gateway, number_format($p->amount, 2, '.', ''),
                ], ';', '"', '\\');
            }
        });
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pagos_'.now()->format('Ymd_His').'.csv"',
        ]);
    }

    protected function filtered(Request $r): Builder
    {
        return Payment::query()
            ->when($r->filled('status') && $r->status !== 'TODOS', fn ($q) => $q->where('status', $r->status))
            ->when($r->filled('method'), fn ($q) => $q->where('method', $r->method))
            ->when($r->filled('program_id'), fn ($q) => $q->whereHas('submission', fn ($s) => $s->where('program_id', $r->program_id)))
            ->when($r->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $r->from))
            ->when($r->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $r->to))
            ->when($r->filled('search'), function ($q) use ($r) {
                $s = '%'.$r->search.'%';
                $q->where(fn ($w) => $w->where('code', 'like', $s)->orWhere('transaction_code', 'like', $s)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $s)->orWhere('student_code', 'like', $s))
                    ->orWhereHas('submission', fn ($x) => $x->where('code', 'like', $s)->orWhere('procedure_name', 'like', $s)));
            });
    }

    // ------------------------------------------------------------------ Helpers

    protected function guardPayable(Request $request, Payment $payment): void
    {
        abort_unless($payment->user_id === $request->user()->id, 403, 'Este ticket no te pertenece.');
        if ($payment->status === 'PAGADO') {
            throw ValidationException::withMessages(['payment' => 'Este ticket ya fue pagado.']);
        }
        if ($payment->status === 'VENCIDO' || ($payment->expires_at && $payment->expires_at->isPast())) {
            throw ValidationException::withMessages(['payment' => 'Este ticket venció (pasaron '.Payment::DAYS_TO_PAY.' días hábiles). Vuelve a solicitar el trámite.']);
        }
        if ($payment->status !== 'PENDIENTE') {
            throw ValidationException::withMessages(['payment' => 'Este ticket ya no se puede pagar.']);
        }
    }

    protected function guardViewer(Request $request, Payment $payment): void
    {
        $u = $request->user();
        abort_unless($payment->user_id === $u->id || $u->isAdmin() || WorkflowService::isTreasury($u), 403);
    }

    protected function guardTreasury(Request $request): void
    {
        $u = $request->user();
        abort_unless($u->isAdmin() || WorkflowService::isTreasury($u), 403, 'Solo Tesorería y Administración ven los pagos.');
    }

    protected function paidResponse(Payment $payment, $submission): array
    {
        return [
            'paid' => true,
            'data' => $payment->fresh()->toPublic(true),
            'submission' => $submission ? Presenter::submissionRow($submission->fresh()) : null,
        ];
    }

    protected function txCode(): string
    {
        return 'TX-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
    }

    protected function luhn(string $n): bool
    {
        $sum = 0;
        foreach (array_reverse(str_split($n)) as $i => $d) {
            $d = (int) $d * ($i % 2 ? 2 : 1);
            $sum += $d > 9 ? $d - 9 : $d;
        }

        return $sum % 10 === 0;
    }
}
