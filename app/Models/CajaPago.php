<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago registrado en la Caja de la UDH (simulación del sistema de caja). */
class CajaPago extends Model
{
    protected $table = 'caja_pagos';

    protected $fillable = [
        'receipt_number', 'student_code', 'student_name', 'concept', 'procedure_code', 'debt_code',
        'amount', 'cashier', 'paid_at', 'payment_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public static function nextReceipt(): string
    {
        $n = (int) static::max('id') + 1;
        do {
            $code = sprintf('B001-%06d', 4800 + $n++);
        } while (static::where('receipt_number', $code)->exists());

        return $code;
    }
}
