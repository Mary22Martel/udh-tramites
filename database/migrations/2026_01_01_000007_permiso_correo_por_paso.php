<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso por paso: el administrador decide en cada trámite qué áreas pueden enviar
 * documentos al correo del estudiante (el último paso siempre lo envía automáticamente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_steps', function (Blueprint $table) {
            $table->boolean('can_email')->default(false);
        });
        Schema::table('submission_documents', function (Blueprint $table) {
            $table->unsignedInteger('step_number')->nullable(); // paso del flujo en que se subió
        });
    }

    public function down(): void
    {
        Schema::table('procedure_steps', fn (Blueprint $t) => $t->dropColumn('can_email'));
        Schema::table('submission_documents', fn (Blueprint $t) => $t->dropColumn('step_number'));
    }
};
