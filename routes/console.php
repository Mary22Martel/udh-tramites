<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Anula los tickets que no se pagaron en 5 días hábiles
Artisan::command('udh:vencer-pagos', function () {
    $n = app(\App\Services\WorkflowService::class)->expireOverdue();
    $this->info("{$n} ticket(s) vencido(s) anulados.");
})->purpose('Anula las solicitudes que no se pagaron a tiempo');

\Illuminate\Support\Facades\Schedule::command('udh:vencer-pagos')->hourly();
