<?php

namespace App\Services;

use App\Models\CajaPago;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Integración con la Caja de la UDH.
 *
 * Cómo funciona en la universidad:
 *  1. El estudiante solicita un trámite con costo → se carga una DEUDA a su código de estudiante.
 *  2. Va a Caja y da su código → el cajero ve sus pagos pendientes y le cobra.
 *  3. El sistema de trámites lee ese pago de caja → el ticket queda pagado y el trámite pasa a "en proceso".
 *
 * HOY: la caja es una simulación (tabla caja_pagos; las deudas pendientes son los tickets PENDIENTE).
 * MAÑANA: con el sistema de caja real solo cambian pendingDebts()/fetch() (leer de su API o base de datos).
 */
class CajaService
{
    public function __construct(protected WorkflowService $wf) {}

    /** Pagos en caja de un código de estudiante (lo que devolvería el sistema de caja). */
    public function fetch(string $studentCode): Collection
    {
        return CajaPago::with('payment.submission.procedure.steps', 'payment.submission.program', 'payment.submission.faculty')
            ->where('student_code', $studentCode)
            ->orderByDesc('paid_at')
            ->get();
    }

    /** Deudas pendientes cargadas al código del estudiante: lo que ve el cajero al buscar su código. */
    public function pendingDebts(string $studentCode): Collection
    {
        return Payment::with('submission:id,code,procedure_name,procedure_code')
            ->whereHas('user', fn ($q) => $q->where('student_code', $studentCode))
            ->where('status', 'PENDIENTE')
            ->orderBy('expires_at')
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'debt_code' => $p->code,
                'concept' => $p->concept,
                'amount' => $p->amount,
                'expires_at' => $p->expires_at?->toIso8601String(),
                'days_left' => $p->expires_at ? \App\Support\BusinessDays::remaining($p->expires_at) : null,
                'created_at' => $p->created_at?->toIso8601String(),
                'tramite' => $p->submission ? ['id' => $p->submission->id, 'code' => $p->submission->code, 'name' => $p->submission->procedure_name] : null,
            ]);
    }

    /**
     * Busca pagos de caja que correspondan a tickets pendientes del estudiante y los marca como pagados.
     * Coincidencia: por la deuda que cobró el cajero o, si no viene, por código de trámite + monto.
     *
     * @return int cuántos tickets se pagaron
     */
    public function sync(User $student): int
    {
        if (! $student->student_code) {
            return 0;
        }

        $rows = CajaPago::whereNull('payment_id')->where('student_code', $student->student_code)->orderBy('paid_at')->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $paid = 0;
        foreach ($rows as $row) {
            $tickets = Payment::with('submission')->where('user_id', $student->id)->where('status', 'PENDIENTE');
            $ticket = $row->debt_code
                ? (clone $tickets)->where('code', $row->debt_code)->first()
                : null;
            $ticket ??= $row->procedure_code
                ? (clone $tickets)->whereHas('submission', fn ($q) => $q->where('procedure_code', $row->procedure_code))
                    ->where('amount', $row->amount)->orderBy('id')->first()
                : null;

            if (! $ticket) {
                continue;
            }

            $row->update(['payment_id' => $ticket->id]);
            $this->wf->markPaid($ticket, 'CAJA', $row->receipt_number, $row->receipt_number, 'CAJA');
            $paid++;
        }

        return $paid;
    }

    /** SIMULACIÓN: el cajero cobra una deuda pendiente del estudiante (como lo haría el sistema de caja). */
    public function simulate(Payment $ticket, ?string $cashier = null): CajaPago
    {
        $ticket->loadMissing('user', 'submission');

        return CajaPago::create([
            'receipt_number' => CajaPago::nextReceipt(),
            'student_code' => $ticket->user->student_code,
            'student_name' => $ticket->user->name,
            'concept' => $ticket->concept,
            'procedure_code' => $ticket->submission?->procedure_code,
            'debt_code' => $ticket->code,
            'amount' => $ticket->amount,
            'cashier' => $cashier ?: 'Caja 1 · Sede central',
            'paid_at' => now(),
        ]);
    }

    /** Fila lista para mostrar en pantalla. */
    public function present(CajaPago $row): array
    {
        $s = $row->payment?->submission;
        $step = $s ? $this->wf->currentStep($s) : null;

        return [
            'id' => $row->id,
            'receipt_number' => $row->receipt_number,
            'concept' => $row->concept,
            'amount' => $row->amount,
            'cashier' => $row->cashier,
            'paid_at' => $row->paid_at?->toIso8601String(),
            'debt_code' => $row->payment?->code ?? $row->debt_code,
            'payment_id' => $row->payment_id,
            'tramite' => $s ? [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->procedure_name,
                'status' => $s->status,
                'area' => in_array($s->status, ['EN_PROCESO', 'OBSERVADO'], true) && $step ? $this->wf->areaLabel($step->area, $s) : null,
            ] : null,
        ];
    }
}
