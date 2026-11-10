<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pasarela de pagos SIMULADA para la demo.
 * Para producción se reemplaza el método simulate() por la API real
 * (Mercado Pago, Niubiz, Culqi, Izipay…): el resto del flujo no cambia.
 *
 * Datos de prueba:
 *  - Yape / Plin: celular de 9 dígitos que empiece en 9 + código de aprobación de 6 dígitos.
 *    El código 000000 simula un pago rechazado.
 *  - Tarjeta: 4111 1111 1111 1111 (aprobada) · 4000 0000 0000 0002 (rechazada por fondos).
 */
class PaymentController extends Controller
{
    /** Pago aprobado y aún no usado del estudiante para ese trámite (si volvió a la página). */
    public function pending(Request $request)
    {
        $request->validate(['procedure_id' => ['required', 'exists:procedures,id']]);
        $p = $this->unused($request->user()->id, (int) $request->procedure_id);

        return ['data' => $p?->toPublic()];
    }

    public function simulate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isStudent(), 403, 'Solo los estudiantes pagan trámites.');

        $data = $request->validate([
            'procedure_id' => ['required', 'exists:procedures,id'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'phone' => ['required_if:method,YAPE,PLIN', 'nullable', 'regex:/^9\d{8}$/'],
            'otp' => ['required_if:method,YAPE,PLIN', 'nullable', 'digits:6'],
            'card_number' => ['required_if:method,TARJETA', 'nullable', 'string'],
            'card_exp' => ['required_if:method,TARJETA', 'nullable', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'card_cvv' => ['required_if:method,TARJETA', 'nullable', 'digits_between:3,4'],
            'card_name' => ['required_if:method,TARJETA', 'nullable', 'string', 'max:80'],
        ], [
            'phone.regex' => 'El celular debe tener 9 dígitos y empezar con 9.',
            'phone.required_if' => 'Ingresa tu número de celular.',
            'otp.required_if' => 'Ingresa el código de aprobación de tu app.',
            'otp.digits' => 'El código de aprobación tiene 6 dígitos.',
            'card_number.required_if' => 'Ingresa el número de tarjeta.',
            'card_exp.required_if' => 'Ingresa la fecha de vencimiento.',
            'card_exp.regex' => 'La fecha de vencimiento debe tener el formato MM/AA.',
            'card_cvv.required_if' => 'Ingresa el CVV.',
            'card_cvv.digits_between' => 'El CVV tiene 3 o 4 dígitos.',
            'card_name.required_if' => 'Ingresa el nombre del titular.',
        ]);

        $procedure = Procedure::findOrFail($data['procedure_id']);
        if (! $procedure->requires_payment || $procedure->cost <= 0) {
            throw ValidationException::withMessages(['procedure_id' => 'Este trámite no requiere pago.']);
        }

        // Evita cobrar dos veces el mismo trámite
        if ($existing = $this->unused($user->id, $procedure->id)) {
            return ['data' => $existing->toPublic(), 'already_paid' => true];
        }

        [$reference, $failure] = $data['method'] === 'TARJETA' ? $this->checkCard($data) : $this->checkWallet($data);

        usleep(900_000); // simula la espera de la pasarela

        $payment = Payment::create([
            'user_id' => $user->id,
            'procedure_id' => $procedure->id,
            'method' => $data['method'],
            'amount' => $procedure->cost,
            'status' => $failure ? 'RECHAZADO' : 'APROBADO',
            'transaction_code' => $failure ? null : $this->newCode(),
            'reference' => $reference,
            'failure_reason' => $failure,
            'paid_at' => $failure ? null : now(),
        ]);

        if ($failure) {
            throw ValidationException::withMessages(['payment' => "Pago rechazado: {$failure}"]);
        }

        return ['data' => $payment->toPublic(), 'already_paid' => false];
    }

    protected function checkWallet(array $d): array
    {
        $ref = substr($d['phone'], 0, 1).'•• ••• '.substr($d['phone'], -3);

        return [$ref, $d['otp'] === '000000' ? 'el código de aprobación es inválido o ya expiró.' : null];
    }

    protected function checkCard(array $d): array
    {
        $num = preg_replace('/\D/', '', $d['card_number']);
        if (strlen($num) < 15 || strlen($num) > 16 || ! $this->luhn($num)) {
            throw ValidationException::withMessages(['card_number' => 'El número de tarjeta no es válido.']);
        }
        [$mm, $yy] = explode('/', $d['card_exp']);
        if (now()->startOfMonth()->gt(now()->setDate(2000 + (int) $yy, (int) $mm, 1)->startOfMonth())) {
            throw ValidationException::withMessages(['card_exp' => 'La tarjeta está vencida.']);
        }
        $ref = '•••• '.substr($num, -4);

        return [$ref, str_ends_with($num, '0002') ? 'la tarjeta no tiene fondos suficientes.' : null];
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

    protected function unused(int $userId, int $procedureId): ?Payment
    {
        return Payment::where('user_id', $userId)->where('procedure_id', $procedureId)
            ->where('status', 'APROBADO')->whereNull('submission_id')->latest('id')->first();
    }

    protected function newCode(): string
    {
        do {
            $code = 'TX-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (Payment::where('transaction_code', $code)->exists());

        return $code;
    }
}
