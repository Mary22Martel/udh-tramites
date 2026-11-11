<?php

namespace App\Http\Middleware;

use App\Services\WorkflowService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Anula los tickets vencidos (5 días hábiles sin pagar).
 * Se revisa como máximo una vez por minuto, así no hace falta configurar tareas programadas en Windows.
 * (En un servidor también corre con: php artisan schedule:work → comando udh:vencer-pagos).
 */
class ExpireOverduePayments
{
    public function handle(Request $request, Closure $next)
    {
        if (Cache::add('udh:expire-payments', true, 60)) {
            try {
                app(WorkflowService::class)->expireOverdue();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
