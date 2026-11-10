<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura organizacional de la universidad:
 * facultades, programas académicos (carreras), áreas centrales y ventanillas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('dean_name')->nullable();
            $table->string('dean_email')->nullable();
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('level')->default('PREGRADO'); // PREGRADO | MAESTRIA | DOCTORADO | SEGUNDA_ESPECIALIDAD
            $table->string('modalities')->nullable();     // Presencial, Semipresencial, A distancia…
            $table->text('aliases')->nullable();          // nombres con que aparece en reportes (separados por |)
            $table->string('director_name')->nullable();
            $table->string('director_email')->nullable();
            $table->timestamps();
        });

        // Campos extra del usuario (estudiantes y personal)
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('ESTUDIANTE')->index();
            $table->string('avatar')->nullable();
            $table->string('dni', 15)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('student_code')->nullable()->index();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_semester')->nullable();
            $table->string('academic_state')->nullable(); // REGULAR | EGRESADO | GRADUADO
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // TESORERIA, MATRICULA, ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('has_channels')->default(false);
            $table->timestamps();
        });

        Schema::create('area_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('area_channel_program', function (Blueprint $table) {
            $table->foreignId('area_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->primary(['area_channel_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_channel_program');
        Schema::dropIfExists('area_channels');
        Schema::dropIfExists('areas');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faculty_id');
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn(['role', 'avatar', 'dni', 'phone', 'student_code', 'current_semester',
                'academic_state', 'area_id', 'job_title', 'department', 'is_active']);
        });
        Schema::dropIfExists('programs');
        Schema::dropIfExists('faculties');
    }
};
