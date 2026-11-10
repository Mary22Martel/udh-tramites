<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ticket de pago de una solicitud. */
class Payment extends Model
{
    public const STATUSES = ['PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado', 'ANULADO' => 'Anulado'];

    public const METHODS = [
        'TARJETA' => 'Tarjeta',
        'YAPE' => 'Yape',
        'PLIN' => 'Plin',
        'BILLETERA' => 'Billetera móvil',
        'BANCA_MOVIL' => 'Banca móvil',
        'AGENTE' => 'Agente / bodega',
    ];

    protected $fillable = [
        'code', 'submission_id', 'user_id', 'procedure_id', 'concept', 'amount', 'currency', 'status',
        'method', 'reference', 'transaction_code', 'gateway', 'gateway_order_id', 'gateway_charge_id',
        'attempts', 'failure_reason', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function nextCode(): string
    {
        $year = now()->year;
        $n = static::where('code', 'like', "TK-{$year}-%")->count() + 1;
        do {
            $code = sprintf('TK-%d-%06d', $year, $n++);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function toPublic(bool $withSubmission = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'concept' => $this->concept,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'method' => $this->method,
            'method_label' => self::METHODS[$this->method] ?? $this->method,
            'reference' => $this->reference,
            'transaction_code' => $this->transaction_code,
            'gateway' => $this->gateway,
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'submission_id' => $this->submission_id,
        ];

        if ($withSubmission) {
            $s = $this->submission;
            $data['submission'] = $s ? [
                'id' => $s->id,
                'code' => $s->code,
                'procedure_name' => $s->procedure_name,
                'procedure_category' => $s->procedure_category,
                'status' => $s->status,
            ] : null;
            $data['student'] = $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'student_code' => $this->user->student_code,
                'email' => $this->user->email,
            ] : null;
            $data['program'] = $s?->program?->only(['id', 'code', 'name']);
        }

        return $data;
    }
}
