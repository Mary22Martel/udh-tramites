<?php

namespace App\Services;

use App\Models\SubmissionDocument;

/**
 * Los documentos de los datos de demostración (seeders) no tienen un archivo real.
 * Al descargarlos se genera este PDF de muestra para que la demo no muestre errores.
 */
class DocumentRenderer
{
    public function render(SubmissionDocument $doc): string
    {
        $s = $doc->submission()->with(['student', 'program'])->first();

        return (new SimplePdf)
            ->heading('UNIVERSIDAD DE HUÁNUCO', 18)
            ->space(4)->rule()->space(10)
            ->heading('DOCUMENTO DE DEMOSTRACIÓN', 14)->space(12)
            ->text("Archivo: {$doc->name}", 11, true)
            ->text("Trámite: {$s->procedure_name}")
            ->text("Expediente: {$s->code}")
            ->text("Estudiante: {$s->student->name} ({$s->student->student_code})")
            ->text('Programa: '.($s->program?->name ?? '-'))
            ->text('Subido por: '.($doc->uploaded_by_name ?? '-').' · '.$doc->created_at?->format('d/m/Y H:i'))
            ->space(16)
            ->text('Este registro fue creado por los datos de prueba del sistema y no tiene un archivo real. En el uso normal aquí se descarga el archivo que subió el área.', 9)
            ->output();
    }
}
