<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Plazo por área: cada paso del flujo tiene sus días hábiles para atender (3 por defecto).
 * - Derivar: un área puede devolver la solicitud a un área anterior.
 * - Pagos con tarjeta: banco emisor y marca de la tarjeta (los informa Culqi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_steps', function (Blueprint $table) {
            $table->unsignedInteger('days')->default(3);            // días hábiles que tiene el área para atender
            $table->boolean('can_return')->default(true);           // puede devolver a un área anterior
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('step_started_at')->nullable();       // cuándo llegó al área actual
            $table->timestamp('step_due_at')->nullable()->index();  // hasta cuándo tiene el área para atender
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('card_brand')->nullable();               // Visa, Mastercard, Amex...
            $table->string('card_issuer')->nullable();              // BCP, Interbank, BBVA, Banco de la Nación...
            $table->string('card_type')->nullable();                // crédito / débito
            $table->string('card_last4', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procedure_steps', fn (Blueprint $t) => $t->dropColumn(['days', 'can_return']));
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn(['step_started_at', 'step_due_at']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['card_brand', 'card_issuer', 'card_type', 'card_last4']));
    }
};
