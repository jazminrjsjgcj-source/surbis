<?php

declare(strict_types=1);

/*
 * Mensajes de validación en español. RNF-GEN-004.
 *
 * Este archivo tenía solo dos claves propias y ninguna de Laravel, así que
 * TODOS los errores de validación del sistema salían sin traducir:
 * "validation.string", "validation.required", "validation.email". En las doce
 * pantallas, desde la Fase 1.
 *
 * Se descubrió al mirar una respuesta 422 de cerca por primera vez. Ninguna
 * prueba lo detectaba: assertSessionHasErrors comprueba que hay error, no que
 * el texto se entienda.
 *
 * Los mensajes hablan de "este campo" y no del nombre técnico. Quien rellena
 * un formulario no sabe que la columna se llama employee_code.
 */

return [
    'accepted' => 'Debes aceptar :attribute.',
    'active_url' => 'No es una dirección válida.',
    'after' => 'La fecha debe ser posterior a :date.',
    'after_or_equal' => 'La fecha debe ser :date o posterior.',
    'alpha' => 'Solo puede contener letras.',
    'alpha_dash' => 'Solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'Solo puede contener letras y números.',
    'array' => 'El valor debe ser una lista.',
    'before' => 'La fecha debe ser anterior a :date.',
    'before_or_equal' => 'La fecha debe ser :date o anterior.',

    'between' => [
        'array' => 'Debe tener entre :min y :max elementos.',
        'file' => 'El archivo debe pesar entre :min y :max kilobytes.',
        'numeric' => 'Debe estar entre :min y :max.',
        'string' => 'Debe tener entre :min y :max caracteres.',
    ],

    'boolean' => 'El valor debe ser sí o no.',
    'confirmed' => 'La confirmación no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => 'No es una fecha válida.',
    'date_equals' => 'La fecha debe ser :date.',
    'date_format' => 'La fecha no tiene el formato :format.',
    'declined' => 'Debes rechazar :attribute.',
    'different' => ':attribute y :other deben ser distintos.',
    'digits' => 'Debe tener :digits dígitos.',
    'digits_between' => 'Debe tener entre :min y :max dígitos.',
    'email' => 'No es un correo electrónico válido.',
    'ends_with' => 'Debe terminar en alguno de estos valores: :values.',
    'enum' => 'El valor seleccionado no es válido.',
    'exists' => 'El valor seleccionado no existe.',
    'file' => 'Debe ser un archivo.',
    'filled' => 'Este campo no puede quedar vacío.',

    'gt' => [
        'array' => 'Debe tener más de :value elementos.',
        'file' => 'El archivo debe pesar más de :value kilobytes.',
        'numeric' => 'Debe ser mayor que :value.',
        'string' => 'Debe tener más de :value caracteres.',
    ],

    'gte' => [
        'array' => 'Debe tener :value elementos o más.',
        'file' => 'El archivo debe pesar :value kilobytes o más.',
        'numeric' => 'Debe ser :value o mayor.',
        'string' => 'Debe tener :value caracteres o más.',
    ],

    'image' => 'Debe ser una imagen.',
    'in' => 'El valor seleccionado no es válido.',
    'integer' => 'Debe ser un número entero.',
    'ip' => 'No es una dirección IP válida.',
    'json' => 'No tiene un formato JSON válido.',

    'lt' => [
        'array' => 'Debe tener menos de :value elementos.',
        'file' => 'El archivo debe pesar menos de :value kilobytes.',
        'numeric' => 'Debe ser menor que :value.',
        'string' => 'Debe tener menos de :value caracteres.',
    ],

    'lte' => [
        'array' => 'Debe tener :value elementos o menos.',
        'file' => 'El archivo debe pesar :value kilobytes o menos.',
        'numeric' => 'Debe ser :value o menor.',
        'string' => 'Debe tener :value caracteres o menos.',
    ],

    'max' => [
        'array' => 'No puede tener más de :max elementos.',
        'file' => 'El archivo no puede pesar más de :max kilobytes.',
        'numeric' => 'No puede ser mayor que :max.',
        'string' => 'No puede tener más de :max caracteres.',
    ],

    'mimes' => 'Debe ser un archivo de tipo: :values.',
    'mimetypes' => 'Debe ser un archivo de tipo: :values.',

    'min' => [
        'array' => 'Debe tener al menos :min elementos.',
        'file' => 'El archivo debe pesar al menos :min kilobytes.',
        'numeric' => 'Debe ser al menos :min.',
        'string' => 'Debe tener al menos :min caracteres.',
    ],

    'not_in' => 'El valor seleccionado no es válido.',
    'not_regex' => 'El formato no es válido.',
    'numeric' => 'Debe ser un número.',
    'present' => 'Este campo debe estar presente.',
    'prohibited' => 'Este campo no está permitido.',
    'regex' => 'El formato no es válido.',
    'required' => 'Este campo es obligatorio.',
    'required_if' => 'Este campo es obligatorio cuando :other es :value.',
    'required_unless' => 'Este campo es obligatorio salvo que :other sea :values.',
    'required_with' => 'Este campo es obligatorio cuando hay :values.',
    'required_without' => 'Este campo es obligatorio cuando no hay :values.',
    'same' => ':attribute y :other deben coincidir.',

    'size' => [
        'array' => 'Debe tener :size elementos.',
        'file' => 'El archivo debe pesar :size kilobytes.',
        'numeric' => 'Debe ser :size.',
        'string' => 'Debe tener :size caracteres.',
    ],

    'starts_with' => 'Debe empezar por alguno de estos valores: :values.',
    'string' => 'Debe ser texto.',
    'timezone' => 'No es una zona horaria válida.',
    'unique' => 'Ese valor ya está en uso.',
    'uploaded' => 'No se pudo subir el archivo.',
    'url' => 'No es una dirección web válida.',
    'ulid' => 'No es un identificador válido.',
    'uuid' => 'No es un identificador válido.',

    /*
     * Mensajes propios.
     *
     * Las dos claves que había antes —question_text_required y
     * option_label_required— se retiraron con sus reglas: el borrador admite
     * campos vacíos y la exigencia se movió a PublicationChecklist. Un mensaje
     * para una regla que ya no existe es código muerto.
     */
    'custom' => [
        'lock_version' => [
            'required' => 'Falta el número de versión del borrador. Recarga la página.',
        ],
    ],

    /*
     * Nombres legibles de los campos.
     *
     * Sin esto, un error dice "El campo employee_code es obligatorio". Quien
     * rellena el formulario no sabe cómo se llama la columna.
     */
    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'code' => 'código',
        'employee_code' => 'código de empleado',
        'first_name' => 'nombre',
        'last_name' => 'apellidos',
        'branch_id' => 'sucursal',
        'area_id' => 'área',
        'role' => 'rol',
        'text' => 'texto',
        'description' => 'descripción',
        'inactivity_seconds' => 'segundos de inactividad',
    ],
];
