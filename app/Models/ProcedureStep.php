<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedureStep extends Model
{
    public const ACTIONS = [
        'SOLICITUD' => 'Presentación de solicitud',
        'VERIFICAR_PAGO' => 'Verificar pago',
        'REVISAR_REQUISITOS' => 'Revisar requisitos',
        'ELABORAR_OFICIO' => 'Elaborar oficio',
        'EMITIR_RESOLUCION' => 'Emitir resolución',
        'EMITIR_CONSTANCIA' => 'Emitir constancia',
        'ANULAR_TALLER' => 'Anular taller / asignatura',
        'FINALIZAR' => 'Finalizar y entregar',
    ];

    public const DOCUMENTS = [
        'OFICIO_PA' => 'Oficio del Programa Académico',
        'RESOLUCION_DECANAL' => 'Resolución Decanal',
        'CONSTANCIA_OFICIAL' => 'Constancia oficial',
        'INFORME_TECNICO' => 'Informe técnico',
    ];

    protected $fillable = [
        'procedure_id', 'step_number', 'name', 'description', 'area', 'action_required',
        'document_requirement', 'can_observe', 'can_reject',
    ];

    protected function casts(): array
    {
        return ['can_observe' => 'boolean', 'can_reject' => 'boolean'];
    }
}
