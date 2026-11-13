<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Conexión con la API de Culqi (v2).
 *  - Tarjeta y Yape: el Checkout del navegador devuelve un token y aquí se crea el cargo (POST /charges).
 *  - Billeteras (Plin…), banca móvil y agentes: se crea una orden (POST /orders), el estudiante paga
 *    y luego se confirma consultando la orden (GET /orders/{id}) o por webhook.
 * Documentación: https://docs.culqi.com
 */
class CulqiGateway
{
    /** Monto mínimo que Culqi acepta para Yape y billeteras móviles (S/ 6 a S/ 500). */
    public const WALLET_MIN = 6.0;

    protected function http()
    {
        $secret = config('services.culqi.secret_key');
        if (! $secret) {
            throw new RuntimeException('Falta configurar CULQI_SECRET_KEY en el archivo .env');
        }

        return Http::withToken($secret)->acceptJson()->asJson()->timeout(30)
            ->baseUrl(rtrim(config('services.culqi.api_url'), '/'));
    }

    /**
     * Crea el cargo con el token del Checkout.
     *
     * @return array{ok:bool, charge_id?:string, reference?:string, method?:string, message?:string}
     */
    public function charge(Payment $payment, string $tokenId, string $email, ?string $methodHint = null): array
    {
        $res = $this->http()->post('/charges', [
            'amount' => (int) round($payment->amount * 100),   // en céntimos
            'currency_code' => $payment->currency,
            'email' => $email,
            'source_id' => $tokenId,
            'description' => Str::limit($payment->concept, 80, ''),
            'capture' => true,
            'metadata' => ['ticket' => $payment->code, 'expediente' => $payment->submission?->code],
        ]);

        $json = $res->json() ?? [];
        if (! $res->successful() || ($json['object'] ?? null) === 'error') {
            return ['ok' => false, 'message' => $json['user_message'] ?? $json['merchant_message'] ?? 'Culqi rechazó el pago.'];
        }
        // Culqi puede pedir autenticación 3DS (action_code REVIEW): en ese caso NO se creó el cargo
        if (! empty($json['action_code']) || ($json['object'] ?? null) !== 'charge' || empty($json['id'])) {
            return ['ok' => false, 'message' => 'Tu banco pidió una autenticación adicional (3DS) que no está habilitada. Prueba con otra tarjeta o con Yape.'];
        }

        $src = $json['source'] ?? [];
        $card = $src['card_number'] ?? $src['source']['card_number'] ?? null;
        $iin = $src['iin'] ?? $src['source']['iin'] ?? [];
        $isYape = str_starts_with($tokenId, 'ype') || strtolower($methodHint ?? '') === 'yape';

        return [
            'ok' => true,
            'charge_id' => $json['id'] ?? null,
            'reference' => $card ? '•••• '.substr($card, -4) : ($isYape ? 'Yape' : null),
            'method' => $isYape ? 'YAPE' : 'TARJETA',
            // Culqi informa el banco emisor y la marca de la tarjeta (iin): así Tesorería ve "Tarjeta BCP", "Tarjeta Interbank"...
            'card' => $isYape ? null : [
                'card_brand' => $iin['card_brand'] ?? null,
                'card_issuer' => Payment::shortBank($iin['issuer']['name'] ?? null),
                'card_type' => isset($iin['card_type']) ? Str::lower($iin['card_type']) : null,
                'card_last4' => $card ? substr($card, -4) : ($src['last_four'] ?? null),
            ],
        ];
    }

    /** Crea (o reutiliza) la orden de Culqi para pagar con billeteras / banca móvil / agentes. */
    public function createOrder(Payment $payment): string
    {
        if ($payment->gateway_order_id && ! $this->orderIsExpired($payment->gateway_order_id)) {
            return $payment->gateway_order_id;
        }
        $user = $payment->user;
        $parts = preg_split('/\s+/', trim(preg_replace('/^(Ing|Lic|Mg|Dr|Dra)\.\s*/u', '', $user->name)));

        $res = $this->http()->post('/orders', [
            'amount' => (int) round($payment->amount * 100),
            'currency_code' => $payment->currency,
            'description' => Str::limit($payment->concept, 80, ''),
            // Único siempre: si se reinicia la base de datos los códigos TK-... se repiten y Culqi rechaza números de orden repetidos
            'order_number' => $payment->code.'-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
            'client_details' => [
                'first_name' => $parts[0] ?? 'Estudiante',
                'last_name' => implode(' ', array_slice($parts, 1)) ?: 'UDH',
                'email' => $user->email,
                'phone_number' => $user->phone ?: '900000000',
            ],
            'expiration_date' => now()->addDay()->timestamp,
            'confirm' => false,
        ]);

        $json = $res->json() ?? [];
        if (! $res->successful() || empty($json['id'])) {
            throw new RuntimeException($json['merchant_message'] ?? $json['user_message'] ?? 'No se pudo crear la orden en Culqi.');
        }
        $payment->update(['gateway_order_id' => $json['id']]);

        return $json['id'];
    }

    /** Las órdenes duran 1 día: si venció hay que crear otra. */
    protected function orderIsExpired(string $orderId): bool
    {
        $res = $this->http()->get('/orders/'.$orderId);

        return ! $res->successful() || in_array($res->json('state'), ['expired', 'deleted', 'expirada'], true);
    }

    /** Consulta la orden en Culqi. Devuelve true si ya está pagada. */
    public function orderIsPaid(string $orderId): bool
    {
        $res = $this->http()->get('/orders/'.$orderId);

        return $res->successful() && in_array($res->json('state'), ['paid', 'pagada'], true);
    }
}
