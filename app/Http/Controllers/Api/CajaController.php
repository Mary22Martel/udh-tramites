<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\CajaService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

/** Consulta de pagos hechos en la Caja de la UDH, por código de estudiante. */
class CajaController extends Controller
{
    public function consulta(Request $request, CajaService $caja)
    {
        $user = $request->user();
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Ingresa un código de estudiante.'])['code']);
        $isStaff = $user->isAdmin() || WorkflowService::isTreasury($user);

        // El estudiante solo puede ver sus propios pagos
        abort_if(! $isStaff && $code !== $user->student_code, 403, 'Solo puedes consultar los pagos de tu propio código ('.$user->student_code.').');

        $student = User::with('program:id,name')->where('student_code', $code)->first();
        if ($student) {
            $caja->sync($student); // vincula pagos nuevos con sus tickets pendientes
        }

        $rows = $caja->fetch($code);
        $pending = $caja->pendingDebts($code);
        abort_if(! $student && $rows->isEmpty(), 404, "No encontramos pagos en caja para el código {$code}.");

        return [
            'student' => [
                'code' => $code,
                'name' => $student?->name ?? $rows->first()?->student_name,
                'program' => $student?->program?->name,
            ],
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
            'tramites' => $rows->whereNotNull('payment_id')->count(),
            'pending' => $pending->values(),
            'pending_total' => (float) $pending->sum('amount'),
            'can_charge' => $isStaff && (bool) config('services.caja.simulate'),
            'data' => $rows->map(fn ($r) => $caja->present($r))->values(),
        ];
    }

    /** SIMULACIÓN DE CAJA: Tesorería cobra una deuda pendiente como lo haría el cajero. */
    public function cobrar(Request $request, Payment $payment, CajaService $caja)
    {
        $u = $request->user();
        abort_unless($u->isAdmin() || WorkflowService::isTreasury($u), 403);
        abort_unless(config('services.caja.simulate'), 404);
        abort_if($payment->status !== 'PENDIENTE', 422, 'Esta deuda ya no está pendiente.');

        $row = $caja->simulate($payment, 'Caja · '.$u->name);
        $caja->sync($payment->user);

        return ['ok' => true, 'receipt_number' => $row->receipt_number, 'message' => "Cobrado. Boleta {$row->receipt_number}. El trámite ya pasó a «en proceso»."];
    }
}
