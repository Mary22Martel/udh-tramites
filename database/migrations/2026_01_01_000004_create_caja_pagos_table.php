<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos hechos en la Caja de la UDH.
 * Por ahora es una SIMULACIÓN del sistema de caja: en producción estos datos
 * se jalarían del sistema de caja real (API o base de datos de la universidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();               // N° de boleta / recibo de caja
            $table->string('student_code')->index();                  // código del estudiante
            $table->string('student_name')->nullable();
            $table->string('concept');                                // concepto cobrado
            $table->string('procedure_code')->nullable();             // código del trámite (si es un trámite)
            $table->string('debt_code')->nullable()->index();         // deuda que el sistema cargó al código del estudiante (si es un trámite)
            $table->decimal('amount', 10, 2);
            $table->string('cashier')->nullable();                    // caja / cajero que atendió
            $table->timestamp('paid_at');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete(); // ticket del sistema que pagó
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_pagos');
    }
};
