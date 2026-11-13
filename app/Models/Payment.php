<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ticket de pago de una solicitud. */
class Payment extends Model
{
    public const STATUSES = ['PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado', 'ANULADO' => 'Anulado', 'VENCIDO' => 'Vencido'];

    /** Días hábiles que tiene el estudiante para pagar su ticket. */
    public const DAYS_TO_PAY = 5;

    public const METHODS = [
        'TARJETA' => 'Tarjeta',
        'YAPE' => 'Yape',
        'PLIN' => 'Plin',
        'BILLETERA' => 'Billetera móvil',
        'BANCA_MOVIL' => 'Banca móvil',
        'AGENTE' => 'Agente / bodega',
        'CAJA' => 'Caja UDH',
    ];

    protected $fillable = [
        'code', 'submission_id', 'user_id', 'procedure_id', 'concept', 'amount', 'currency', 'status',
        'method', 'reference', 'transaction_code', 'gateway', 'gateway_order_id', 'gateway_charge_id',
        'attempts', 'failure_reason', 'paid_at', 'expires_at',
        'card_brand', 'card_issuer', 'card_type', 'card_last4',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float', 'paid_at' => 'datetime', 'expires_at' => 'datetime'];
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

    /** Bancos más comunes en Perú: nombre que entrega Culqi → nombre corto para mostrar. */
    public const BANKS = [
        'CREDITO' => 'BCP', 'BCP' => 'BCP',
        'INTERBANK' => 'Interbank', 'INTERNACIONAL DEL PERU' => 'Interbank',
        'BBVA' => 'BBVA', 'CONTINENTAL' => 'BBVA',
        'SCOTIABANK' => 'Scotiabank',
        'NACION' => 'Banco de la Nación',
        'PICHINCHA' => 'Banco Pichincha', 'FINANCIERO' => 'Banco Pichincha',
        'BANBIF' => 'BanBif', 'INTERAMERICANO' => 'BanBif',
        'FALABELLA' => 'Banco Falabella', 'RIPLEY' => 'Banco Ripley',
        'GNB' => 'Banco GNB', 'CAJA AREQUIPA' => 'Caja Arequipa', 'CAJA HUANCAYO' => 'Caja Huancayo',
    ];

    /** Convierte el nombre del banco emisor que da Culqi (p. ej. "BANCO DE CREDITO DEL PERU") a uno corto ("BCP"). */
    public static function shortBank(?string $issuer): ?string
    {
        if (! $issuer) {
            return null;
        }
        $up = mb_strtoupper(\Illuminate\Support\Str::ascii($issuer));
        foreach (self::BANKS as $needle => $short) {
            if (str_contains($up, $needle)) {
                return $short;
            }
        }

        return \Illuminate\Support\Str::title(mb_strtolower($issuer));
    }

    /**
     * SOLO para la pasarela de prueba y los datos de demostración: inventa el banco y la marca
     * a partir del número (con Culqi real estos datos vienen de Culqi).
     */
    public static function simulatedCard(?string $number = null): array
    {
        $number = preg_replace('/\D/', '', (string) $number) ?: (string) random_int(4000_0000, 5599_9999).'00001111';
        $banks = ['BCP', 'BCP', 'BCP', 'Interbank', 'Interbank', 'BBVA', 'BBVA', 'Banco de la Nación', 'Scotiabank', 'Banco Pichincha'];
        $brand = match ($number[0]) { '4' => 'Visa', '5', '2' => 'Mastercard', '3' => 'American Express', default => 'Visa' };

        return [
            'card_brand' => $brand,
            'card_issuer' => $banks[crc32(substr($number, 0, 8).substr($number, -4)) % count($banks)],
            'card_type' => crc32($number) % 3 === 0 ? 'crédito' : 'débito',
            'card_last4' => substr($number, -4),
        ];
    }

    /** "Tarjeta BCP · Visa ****1111" / "Yape" / "Caja UDH". */
    public function methodDetail(): ?string
    {
        if (! $this->method) {
            return null;
        }
        if ($this->method !== 'TARJETA') {
            return self::METHODS[$this->method] ?? $this->method;
        }

        return trim('Tarjeta '.($this->card_issuer ?: '').($this->card_brand ? ' · '.$this->card_brand : '').($this->card_last4 ? ' ****'.$this->card_last4 : ''));
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
            'card_brand' => $this->card_brand,
            'card_issuer' => $this->card_issuer,
            'card_type' => $this->card_type,
            'card_last4' => $this->card_last4,
            'method_detail' => $this->methodDetail(),
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'days_left' => $this->status === 'PENDIENTE' && $this->expires_at ? \App\Support\BusinessDays::remaining($this->expires_at) : null,
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
