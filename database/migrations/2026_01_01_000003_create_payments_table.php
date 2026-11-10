<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos (tickets) de los trámites.
 * Al enviar una solicitud con costo se genera un ticket PENDIENTE; el estudiante lo paga
 * desde "Mis pagos" con la pasarela (Culqi o simulador) y pasa a PAGADO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                         // N° de ticket: TK-2026-000123
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('concept');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PEN');
            $table->string('status')->default('PENDIENTE')->index();  // PENDIENTE | PAGADO | ANULADO
            $table->string('method')->nullable();                     // TARJETA | YAPE | PLIN | BILLETERA | BANCA_MOVIL | AGENTE
            $table->string('reference')->nullable();                  // tarjeta o celular enmascarado (nunca completo)
            $table->string('transaction_code')->nullable();           // código que ve el estudiante
            $table->string('gateway')->nullable();                    // CULQI | SIMULADO
            $table->string('gateway_order_id')->nullable()->index();  // ord_test_… (billeteras / PagoEfectivo)
            $table->string('gateway_charge_id')->nullable();          // chr_test_…
            $table->unsignedInteger('attempts')->default(0);
            $table->string('failure_reason')->nullable();             // último intento rechazado
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
