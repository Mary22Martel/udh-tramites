<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correo propio de cada área, programa y facultad.
 * Con su contraseña de aplicación, el sistema envía los documentos DESDE su cuenta
 * y Gmail guarda el correo en sus "Enviados".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['areas', 'programs', 'faculties'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->text('mail_password')->nullable();          // contraseña de aplicación (se guarda cifrada)
                $table->timestamp('mail_verified_at')->nullable();  // última prueba exitosa
            });
        }
    }

    public function down(): void
    {
        foreach (['areas', 'programs', 'faculties'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn(['mail_password', 'mail_verified_at']));
        }
    }
};
