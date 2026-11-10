<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pagos en línea (pasarela simulada: Yape, Plin, tarjeta). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');                 // YAPE | PLIN | TARJETA
            $table->decimal('amount', 10, 2);
            $table->string('status');                 // APROBADO | RECHAZADO
            $table->string('transaction_code')->nullable()->unique();
            $table->string('reference')->nullable();  // celular o tarjeta enmascarados (nunca el número completo)
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
