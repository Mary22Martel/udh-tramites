<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/** Comprobante de pago en PDF (lo descargan el estudiante y Tesorería). */
class ReceiptRenderer
{
    protected const NAVY = '0F2440';

    protected const TEAL = '2EB8A0';

    protected const GOLD = 'F5B400';

    protected const INK = '0F1E33';

    protected const MUTED = '5D6E84';

    public function render(Payment $p): string
    {
        $p->loadMissing(['user', 'submission.program', 'submission.faculty']);
        $s = $p->submission;
        $u = $p->user;
        Carbon::setLocale('es');
        $pdf = new PdfCanvas;
        $L = 48;
        $R = PdfCanvas::W - 48;

        // Encabezado
        $pdf->rect(0, 0, PdfCanvas::W, 128, self::NAVY)->rect(0, 128, PdfCanvas::W, 4, self::GOLD);
        $pdf->jpeg(resource_path('pdf-escudo.jpg'), $L, 26, 64, 76);
        $pdf->text($L + 80, 58, 'UNIVERSIDAD DE HUÁNUCO', 17, true, 'FFFFFF')
            ->text($L + 80, 77, 'Oficina de Tesorería', 10.5, false, 'C9D4E3')
            ->text($L + 80, 93, 'Comprobante de pago electrónico', 10.5, false, 'C9D4E3');
        $pdf->text($R, 56, 'COMPROBANTE', 9, true, self::TEAL, 'right')
            ->text($R, 76, $p->code, 15, true, 'FFFFFF', 'right');

        // Estado + monto
        $y = 162;
        $pdf->roundRect($L, $y, $R - $L, 92, 10, 'F3F6FA');
        $pdf->text($L + 20, $y + 28, 'MONTO PAGADO', 9, true, self::MUTED)
            ->text($L + 20, $y + 66, 'S/ '.number_format($p->amount, 2), 32, true, self::NAVY);
        $pill = 'PAGADO';
        $pw = $pdf->width($pill, 9, true) + 26;
        $pdf->roundRect($R - 20 - $pw, $y + 16, $pw, 20, 10, self::TEAL)
            ->text($R - 20 - $pw / 2, $y + 30, $pill, 9, true, 'FFFFFF', 'center')
            ->text($R - 20, $y + 58, $p->paid_at?->translatedFormat('j \d\e F \d\e Y') ?? '', 10.5, true, self::INK, 'right')
            ->text($R - 20, $y + 74, ($p->paid_at?->format('H:i') ?? '').' h', 9.5, false, self::MUTED, 'right');

        // Secciones
        $y += 124;
        $y = $this->section($pdf, $L, $R, $y, 'Datos del pago', [
            ...($p->gateway === 'CAJA' ? [
                ['Medio de pago', 'Pago presencial en Caja UDH'],
                ['N° de boleta', $p->reference ?? '—'],
            ] : [
                ['Medio de pago', trim((Payment::METHODS[$p->method] ?? $p->method).' '.$p->reference)],
                ['N° de transacción', $p->transaction_code ?? '—'],
                ['Pasarela', $p->gateway === 'CULQI' ? 'Culqi' : 'Pasarela de prueba'],
            ]),
            ['Concepto', $p->concept],
        ]);
        $y = $this->section($pdf, $L, $R, $y + 30, 'Estudiante', [
            ['Nombre', $u->name],
            ['Código', $u->student_code ?? '—'],
            ['Programa', $s?->program?->name ?? '—'],
            ['Facultad', $s?->faculty?->name ?? '—'],
        ]);
        $y = $this->section($pdf, $L, $R, $y + 30, 'Trámite', [
            ['Trámite', $s?->procedure_name ?? '—'],
            ['Expediente', $s?->code ?? '—'],
        ]);

        // Pie
        $foot = 770;
        $pdf->line($L, $foot, $R, $foot, 'D9DEE7');
        $test = ($p->gateway === 'CAJA' && config('services.caja.simulate')) || ($p->gateway !== 'CULQI' && $p->gateway !== 'CAJA') || str_starts_with((string) $p->gateway_charge_id, 'chr_test');
        $lines = $pdf->wrap('Este comprobante acredita el pago del derecho de trámite indicado. Consérvalo para cualquier consulta en Tesorería.'
            .($test ? ' Pago realizado en entorno de prueba: sin valor real.' : ''), 8.5, $R - $L);
        foreach ($lines as $i => $l) {
            $pdf->text($L, $foot + 16 + $i * 12, $l, 8.5, false, self::MUTED);
        }
        $pdf->text($L, 818, 'Sistema de Gestión de Trámites · Universidad de Huánuco', 8, true, self::NAVY)
            ->text($R, 818, 'Emitido el '.now()->format('d/m/Y H:i'), 8, false, self::MUTED, 'right');

        return $pdf->output();
    }

    /** Tabla de dos columnas con título. Devuelve la Y final. */
    protected function section(PdfCanvas $pdf, float $L, float $R, float $y, string $title, array $rows): float
    {
        $pdf->rect($L, $y - 9, 3, 12, self::TEAL)->text($L + 10, $y + 1, mb_strtoupper($title), 9.5, true, self::NAVY);
        $y += 14;
        $labelW = 130;
        foreach ($rows as [$label, $value]) {
            $lines = $pdf->wrap((string) $value, 10, $R - $L - $labelW - 8, true);
            $h = 12 + count($lines) * 13;
            $pdf->text($L, $y + 16, $label, 9.5, false, self::MUTED);
            foreach ($lines as $i => $line) {
                $pdf->text($L + $labelW, $y + 16 + $i * 13, $line, 10, true, self::INK);
            }
            $y += $h;
            $pdf->line($L, $y + 2, $R, $y + 2, 'E6EAF0', 0.6);
        }

        return $y;
    }
}
