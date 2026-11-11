<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CajaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
| API del sistema (prefijo /api). Usa la sesión de Laravel (cookies),
| así el frontend React no necesita manejar tokens.
*/

// Públicas
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/demo-login', [AuthController::class, 'demoLogin']);
Route::get('/demo-accounts', [AuthController::class, 'demoAccounts']);
Route::post('/webhooks/culqi', [PaymentController::class, 'culqiWebhook']);

Route::middleware(['auth', 'expire.payments'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/meta', MetaController::class);
    Route::get('/dashboard', DashboardController::class);

    // Notificaciones
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);

    // Catálogo de trámites
    Route::get('/procedures', [ProcedureController::class, 'index']);
    Route::get('/procedures/{procedure}', [ProcedureController::class, 'show']);

    // Solicitudes
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::post('/submissions', [SubmissionController::class, 'store']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::post('/submissions/{submission}/approve', [SubmissionController::class, 'approve']);
    Route::post('/submissions/{submission}/observe', [SubmissionController::class, 'observe']);
    Route::post('/submissions/{submission}/reject', [SubmissionController::class, 'reject']);
    Route::post('/submissions/{submission}/correct', [SubmissionController::class, 'correct']);
    Route::get('/documents/{document}/download', [SubmissionController::class, 'download']);

    // Pagos
    Route::get('/payments/config', [PaymentController::class, 'config']);
    Route::get('/payments/mine', [PaymentController::class, 'mine']);
    Route::get('/payments', [PaymentController::class, 'index']);              // Tesorería / Admin
    Route::get('/payments-export', [PaymentController::class, 'export']);      // Tesorería / Admin
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/payments/{payment}/simulate', [PaymentController::class, 'simulate']);
        Route::post('/payments/{payment}/culqi/charge', [PaymentController::class, 'culqiCharge']);
        Route::post('/payments/{payment}/culqi/order', [PaymentController::class, 'culqiOrder']);
        Route::post('/payments/{payment}/culqi/check', [PaymentController::class, 'culqiCheck']);
        Route::post('/payments/{payment}/caja/check', [PaymentController::class, 'cajaCheck']);
        Route::post('/payments/{payment}/caja/simulate', [PaymentController::class, 'cajaSimulate']);
    });

    // Caja UDH: pagos hechos en caja por código de estudiante
    Route::get('/caja', [CajaController::class, 'consulta'])->middleware('throttle:30,1');
    Route::post('/caja/cobrar/{payment}', [CajaController::class, 'cobrar']); // simulación del cajero

    // Solo administrador
    Route::middleware('role:ADMINISTRADOR')->group(function () {
        Route::get('/submissions-export', [SubmissionController::class, 'export']);
        Route::get('/reports/programs', [ReportController::class, 'byProgram']);

        Route::post('/procedures', [ProcedureController::class, 'store']);
        Route::put('/procedures/{procedure}', [ProcedureController::class, 'update']);
        Route::delete('/procedures/{procedure}', [ProcedureController::class, 'destroy']);
        Route::post('/procedures/{procedure}/toggle', [ProcedureController::class, 'toggle']);
        Route::post('/procedures/{procedure}/bifurcate', [ProcedureController::class, 'bifurcate']);
        Route::post('/procedures-bulk', [ProcedureController::class, 'bulk']);

        Route::get('/faculties', [OrganizationController::class, 'faculties']);
        Route::post('/faculties', [OrganizationController::class, 'saveFaculty']);
        Route::put('/faculties/{faculty}', [OrganizationController::class, 'saveFaculty']);
        Route::delete('/faculties/{faculty}', [OrganizationController::class, 'deleteFaculty']);

        Route::get('/programs', [OrganizationController::class, 'programs']);
        Route::post('/programs', [OrganizationController::class, 'saveProgram']);
        Route::put('/programs/{program}', [OrganizationController::class, 'saveProgram']);
        Route::delete('/programs/{program}', [OrganizationController::class, 'deleteProgram']);

        Route::get('/areas', [OrganizationController::class, 'areas']);
        Route::post('/areas', [OrganizationController::class, 'saveArea']);
        Route::put('/areas/{area}', [OrganizationController::class, 'saveArea']);
        Route::delete('/areas/{area}', [OrganizationController::class, 'deleteArea']);
        Route::put('/areas/{area}/channels', [OrganizationController::class, 'saveChannels']);

        Route::get('/staff', [OrganizationController::class, 'staff']);
        Route::get('/students', [OrganizationController::class, 'students']);
        Route::post('/users', [OrganizationController::class, 'saveUser']);
        Route::put('/users/{user}', [OrganizationController::class, 'saveUser']);
        Route::post('/users/{user}/toggle', [OrganizationController::class, 'toggleUser']);

        Route::get('/imports/types', [ImportController::class, 'types']);
        Route::get('/imports/template/{type}', [ImportController::class, 'template']);
        Route::post('/imports', [ImportController::class, 'import']);
        Route::post('/imports/generate', [ImportController::class, 'generate']);
        Route::post('/imports/reset', [ImportController::class, 'reset']);
    });
});
