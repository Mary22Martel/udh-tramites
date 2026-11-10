<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de trámites (TUPA), sus requisitos y su flujo de pasos,
 * más las solicitudes de los estudiantes, documentos, historial y notificaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->unsignedInteger('estimated_days')->default(3);
            $table->decimal('cost', 10, 2)->default(0);
            $table->boolean('requires_payment')->default(true);
            $table->string('payment_concept')->nullable();
            $table->string('target_scope')->default('TODOS'); // TODOS | FACULTAD | PROGRAMA
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('procedures')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('procedure_program', function (Blueprint $table) {
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->primary(['procedure_id', 'program_id']);
        });

        Schema::create('faculty_procedure', function (Blueprint $table) {
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained()->cascadeOnDelete();
            $table->primary(['procedure_id', 'faculty_id']);
        });

        Schema::create('procedure_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('file_types')->nullable();
            $table->unsignedInteger('max_size_mb')->default(5);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('procedure_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('name');
            $table->text('description')->nullable();
            // ESTUDIANTE | PROGRAMA_ACADEMICO | FACULTAD | código de un área central (TESORERIA, MATRICULA, ...)
            $table->string('area');
            $table->string('action_required')->default('REVISAR_REQUISITOS');
            $table->string('document_requirement')->nullable();
            $table->boolean('can_observe')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedure_code');
            $table->string('procedure_name');
            $table->string('procedure_category');
            $table->decimal('cost', 10, 2)->default(0);
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('status')->default('EN_PROCESO')->index(); // EN_PROCESO | OBSERVADO | FINALIZADO | RECHAZADO
            $table->unsignedInteger('current_step_index')->default(0);
            $table->string('current_area')->nullable()->index();
            $table->foreignId('channel_id')->nullable()->constrained('area_channels')->nullOnDelete();
            $table->string('payment_status')->default('NO_REQUIERE'); // NO_REQUIERE | PENDIENTE | VERIFICADO
            $table->string('payment_method')->nullable();
            $table->string('payment_operation')->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->json('observation')->nullable();
            $table->string('final_document_code')->nullable()->unique();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('submission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // REQUISITO | COMPROBANTE_PAGO | OFICIO | RESOLUCION | CONSTANCIA_FINAL | SUBSANACION
            $table->string('category')->nullable();
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_by_name')->nullable();
            $table->string('official_number')->nullable();
            $table->timestamps();
        });

        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('step_name');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role');
            $table->string('actor_area');
            $table->string('action')->index();
            $table->text('comment')->nullable();
            $table->string('document_name')->nullable();
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('INFO'); // INFO | SUCCESS | WARNING | ALERT
            $table->foreignId('submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('submission_events');
        Schema::dropIfExists('submission_documents');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('procedure_steps');
        Schema::dropIfExists('procedure_requirements');
        Schema::dropIfExists('faculty_procedure');
        Schema::dropIfExists('procedure_program');
        Schema::dropIfExists('procedures');
    }
};
