<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Faculty;
use App\Models\Procedure;
use App\Models\ProcedureStep;
use App\Models\Program;
use App\Models\Submission;
use App\Models\User;

/** Catálogos pequeños que usan los formularios y filtros del frontend. */
class MetaController extends Controller
{
    public function __invoke()
    {
        return [
            'faculties' => Faculty::orderBy('name')->get(['id', 'code', 'name']),
            'programs' => Program::orderBy('name')->get(['id', 'faculty_id', 'code', 'name', 'level']),
            'levels' => Program::LEVELS,
            'areas' => Area::orderBy('name')->get(['id', 'code', 'name', 'has_channels']),
            'categories' => Procedure::CATEGORIES,
            'roles' => User::ROLES,
            'actions' => ProcedureStep::ACTIONS,
            'documents' => ProcedureStep::DOCUMENTS,
            'statuses' => Submission::STATUSES,
            'step_areas' => array_merge(
                ['ESTUDIANTE' => 'Estudiante', 'PROGRAMA_ACADEMICO' => 'Programa Académico del estudiante', 'FACULTAD' => 'Decanato de la Facultad del estudiante'],
                Area::orderBy('name')->pluck('name', 'code')->all()
            ),
            'payment_methods' => [
                'BANCO_NACION' => 'Banco de la Nación',
                'BCP' => 'BCP',
                'BBVA' => 'BBVA',
                'YAPE' => 'Yape',
                'CAJA_UDH' => 'Caja UDH',
            ],
        ];
    }
}
