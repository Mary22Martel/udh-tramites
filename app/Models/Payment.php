<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const METHODS = ['YAPE' => 'Yape', 'PLIN' => 'Plin', 'TARJETA' => 'Tarjeta de débito / crédito'];

    protected $fillable = [
        'user_id', 'procedure_id', 'submission_id', 'method', 'amount', 'status',
        'transaction_code', 'reference', 'failure_reason', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_at' => 'datetime'];
    }

    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'method_label' => self::METHODS[$this->method] ?? $this->method,
            'amount' => $this->amount,
            'status' => $this->status,
            'transaction_code' => $this->transaction_code,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
