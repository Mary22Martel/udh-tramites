<?php

/*
 * Mensajes de validación en español.
 * Los mensajes propios de cada formulario (más claros) están en cada controlador.
 */
return [
    'accepted' => 'Debes aceptar :attribute.',
    'array' => ':Attribute debe ser una lista.',
    'boolean' => ':Attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => ':Attribute no es una fecha válida.',
    'email' => ':Attribute debe ser un correo válido.',
    'exists' => ':Attribute no existe.',
    'file' => ':Attribute debe ser un archivo.',
    'image' => ':Attribute debe ser una imagen.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'max' => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'file' => ':Attribute no puede pesar más de :max KB.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
        'array' => ':Attribute no puede tener más de :max elementos.',
    ],
    'mimes' => ':Attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':Attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'numeric' => ':Attribute debe ser al menos :min.',
        'file' => ':Attribute debe pesar al menos :min KB.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
        'array' => ':Attribute debe tener al menos :min elementos.',
    ],
    'numeric' => ':Attribute debe ser un número.',
    'present' => 'Falta :attribute.',
    'required' => 'Completa :attribute.',
    'required_if' => 'Completa :attribute.',
    'required_with' => 'Completa :attribute.',
    'same' => ':Attribute y :other deben coincidir.',
    'size' => [
        'numeric' => ':Attribute debe ser :size.',
        'file' => ':Attribute debe pesar :size KB.',
        'string' => ':Attribute debe tener :size caracteres.',
        'array' => ':Attribute debe tener :size elementos.',
    ],
    'string' => ':Attribute debe ser texto.',
    'unique' => 'Ya existe un registro con ese :attribute.',
    'uploaded' => 'No se pudo subir :attribute.',
    'url' => ':Attribute debe ser un enlace válido.',

    'custom' => [],

    // Nombres "humanos" de los campos
    'attributes' => [
        'name' => 'el nombre',
        'code' => 'el código',
        'email' => 'el correo',
        'password' => 'la contraseña',
        'description' => 'la descripción',
        'category' => 'la categoría',
        'cost' => 'el costo',
        'comment' => 'el comentario',
        'reason' => 'el motivo',
        'detail' => 'el detalle',
        'file' => 'el archivo',
        'files' => 'los archivos',
        'target' => 'el área de destino',
        'amount' => 'el monto',
        'student_code' => 'el código de estudiante',
        'program_id' => 'la carrera',
        'faculty_id' => 'la facultad',
        'area_id' => 'el área',
        'role' => 'el rol',
        'phone' => 'el teléfono',
        'dni' => 'el DNI',
        'type' => 'el tipo',
        'count' => 'la cantidad',
    ],
];
