<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\SubmissionDocument;
use Illuminate\Support\Carbon;

/** Genera el PDF de los documentos que no fueron subidos como archivo (oficios, resoluciones, constancias). */
class DocumentRenderer
{
    public function render(SubmissionDocument $doc): string
    {
        $s = $doc->submission()->with(['student', 'program', 'faculty'])->first();
        Carbon::setLocale('es');
        $date = $doc->created_at?->translatedFormat('j \d\e F \d\e Y') ?? now()->translatedFormat('j \d\e F \d\e Y');
        $pdf = (new SimplePdf)
            ->heading('UNIVERSIDAD DE HUÁNUCO', 18)
            ->text('"Quaerens Veritatem"', 10)
            ->space(4)->rule()->space(6);

        $student = $s->student;
        $who = "{$student->name}, con código de estudiante N° {$student->student_code}, del Programa Académico de {$s->program?->name} de la {$s->faculty?->name}";

        switch ($doc->type) {
            case 'CONSTANCIA_FINAL':
            case 'CONSTANCIA':
                $pdf->heading('CONSTANCIA', 16)->space(4)
                    ->text($doc->official_number ?? '', 10, true)->space(14)
                    ->text("La Universidad de Huánuco, a través de la Dirección de Matrícula y Registros Académicos, hace constar que {$who}, ha culminado satisfactoriamente el trámite de \"{$s->procedure_name}\" registrado con expediente N° {$s->code}.")
                    ->space(8)
                    ->text('Se expide el presente documento a solicitud del interesado para los fines que estime conveniente.');
                break;
            case 'RESOLUCION':
                $pdf->heading('RESOLUCIÓN DECANAL', 16)->space(4)
                    ->text($doc->official_number ?? '', 10, true)->space(14)
                    ->text("VISTO: el expediente N° {$s->code} presentado por {$who}, sobre \"{$s->procedure_name}\".")
                    ->space(6)
                    ->text('CONSIDERANDO: que el expediente cuenta con la opinión favorable del Programa Académico y cumple con los requisitos establecidos en el Reglamento General de la Universidad.')
                    ->space(6)
                    ->text("SE RESUELVE: APROBAR lo solicitado por el (la) estudiante {$student->name}, y remitir lo actuado a la Dirección de Matrícula y Registros Académicos para los fines correspondientes.");
                break;
            case 'OFICIO':
                $pdf->heading('OFICIO', 16)->space(4)
                    ->text($doc->official_number ?? '', 10, true)->space(14)
                    ->text('Señor(a) Decano(a) de la '.($s->faculty?->name ?? 'Facultad').'.')
                    ->space(6)
                    ->text("Me dirijo a usted para remitir el expediente N° {$s->code} de {$who}, referente a \"{$s->procedure_name}\", el cual ha sido revisado por esta Dirección y cumple con los requisitos exigidos, para su atención y emisión de la resolución correspondiente.")
                    ->space(6)
                    ->text('Atentamente,');
                break;
            default:
                $pdf->heading('DOCUMENTO ADJUNTO', 14)->space(10)
                    ->text("Archivo: {$doc->name}", 11, true)
                    ->text("Tipo: {$doc->type}")
                    ->text("Expediente: {$s->code} - {$s->procedure_name}")
                    ->text("Estudiante: {$student->name} ({$student->student_code})")
                    ->text('Cargado por: '.($doc->uploaded_by_name ?? '-'))
                    ->space(10)
                    ->text('Este es un documento de demostración generado automáticamente porque el registro fue creado por los datos de prueba (seeder) sin un archivo real.', 9);
        }

        $pdf->space(30)->text("Huánuco, {$date}.")->space(50)
            ->text('_______________________________')
            ->text($doc->uploaded_by_name ?? 'Universidad de Huánuco', 10, true)
            ->space(30)->rule();

        if ($s->final_document_code) {
            $pdf->text('Código de verificación: '.$s->final_document_code, 9, true)
                ->text('Verifique la autenticidad en: '.rtrim(config('app.url'), '/').'/verificar/'.$s->final_document_code, 9);
        }
        $pdf->text('Documento emitido por el Sistema de Gestión de Trámites UDH.', 8);

        return $pdf->output();
    }
}
