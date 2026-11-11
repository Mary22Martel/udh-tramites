<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Días hábiles (lunes a viernes, sin feriados nacionales del Perú).
 * Ejemplo: un ticket generado el miércoles 11 con 5 días hábiles vence el miércoles 18 a las 23:59.
 */
class BusinessDays
{
    /** Feriados nacionales de fecha fija (mes-día). Agrega aquí los feriados que falten. */
    public const HOLIDAYS = [
        '01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29',
        '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25',
    ];

    public static function isBusinessDay(Carbon $date): bool
    {
        return ! $date->isWeekend() && ! in_array($date->format('m-d'), self::HOLIDAYS, true);
    }

    /** Suma N días hábiles y devuelve el final de ese día. */
    public static function add(Carbon $from, int $days): Carbon
    {
        $date = $from->copy()->startOfDay();
        while ($days > 0) {
            $date->addDay();
            if (self::isBusinessDay($date)) {
                $days--;
            }
        }

        return $date->endOfDay();
    }

    /** Días hábiles que quedan desde hoy hasta la fecha límite (incluye el día límite). */
    public static function remaining(Carbon $deadline, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        if ($now->gt($deadline)) {
            return 0;
        }
        $n = 0;
        $date = $now->copy()->startOfDay();
        while ($date->lte($deadline)) {
            $date->addDay();
            if ($date->lte($deadline) && self::isBusinessDay($date)) {
                $n++;
            }
        }

        return $n;
    }
}
