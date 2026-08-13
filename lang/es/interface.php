<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Iniciar sesion',
        'subtitle' => 'Encuestas de satisfaccion',
        'heading' => 'Inicia sesion',
        'help' => 'Escribe el correo y la contrasena de tu cuenta.',
        'email' => 'Correo electronico',
        'password' => 'Contrasena',
        'remember' => 'Mantener la sesion iniciada',
        'submit' => 'Entrar',
        'forgot' => 'Olvide mi contrasena',
    ],

    'forgot' => [
        'title' => 'Recuperar contrasena',
        'subtitle' => 'Encuestas de satisfaccion',
        'help' => 'Escribe tu correo y te enviaremos una liga para definir una contrasena nueva.',
        'email' => 'Correo electronico',
        'submit' => 'Enviar liga',
        'back' => 'Volver al acceso',
    ],

    'reset' => [
        'title' => 'Restablecer contrasena',
        'subtitle' => 'Encuestas de satisfaccion',
        'help' => 'Define una contrasena nueva para tu cuenta.',
        'email' => 'Correo electronico',
        'password' => 'Contrasena nueva',
        'confirmation' => 'Confirma la contrasena',
        'submit' => 'Guardar y continuar',
    ],

    'password' => [
        'policy' => 'Al menos :min caracteres, con letras y numeros.',
    ],

    'second_factor' => [
        'title' => 'Verificacion en dos pasos',
        'subtitle' => 'Encuestas de satisfaccion',
        'help' => 'Te enviamos un codigo a :email. Escribelo para continuar.',
        'code' => 'Codigo de verificacion',
        'code_hint' => 'Seis digitos, o uno de tus codigos de recuperacion.',
        'submit' => 'Verificar',
        'resend' => 'Enviar otro codigo',
        'cancel' => 'Cancelar y salir',
    ],

    'security' => [
        'title' => 'Seguridad de la cuenta',
        'mfa_heading' => 'Verificacion en dos pasos',
        'mfa_off' => 'Esta desactivada. Al activarla, cada vez que inicies sesion te pediremos un codigo enviado a tu correo.',
        'mfa_on' => 'Esta activada. Cada inicio de sesion pide un codigo enviado a tu correo.',
        'enable' => 'Activar',
        'disable' => 'Desactivar',
        'enabled' => 'Verificacion en dos pasos activada.',
        'disabled' => 'Verificacion en dos pasos desactivada.',
        'codes_heading' => 'Codigos de recuperacion',
        'codes_help' => 'Guardalos ahora en un lugar seguro. No volveran a mostrarse: si los pierdes, tendras que generar otros.',
        'codes_regenerate' => 'Generar codigos nuevos',
        'codes_regenerated' => 'Codigos de recuperacion nuevos. Los anteriores dejaron de servir.',
        'back' => 'Volver al inicio',
    ],

    'branches' => [
        'title' => 'Sucursales',
        'subtitle' => 'Las sedes y oficinas de tu organizacion.',
        'new' => 'Nueva sucursal',
        'name' => 'Nombre',
        'code' => 'Codigo',
        'code_hint' => 'Letras, numeros, punto, guion y guion bajo. Unico dentro de tu organizacion.',
        'status' => 'Estado',
        'areas' => 'Areas',
        'people' => 'Colaboradores',
        'actions' => 'Acciones',
        'edit' => 'Editar',
        'archive' => 'Archivar',
        'activate' => 'Activar',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'search' => 'Buscar por nombre o codigo',
        'filter_all' => 'Todas',
        'filter_active' => 'Activas',
        'filter_archived' => 'Archivadas',
        'state_active' => 'Activa',
        'state_archived' => 'Archivada',

        'created' => 'Sucursal creada.',
        'updated' => 'Sucursal actualizada.',
        'archived' => 'Sucursal archivada. El historial se conserva.',
        'activated' => 'Sucursal activada.',

        'archive_blocked' => 'No se puede archivar todavia: hay :references. Reasignalos antes.',

        'reference' => [
            'memberships' => ':count colaborador asignado|:count colaboradores asignados',
            'staff_members' => ':count persona evaluable asignada|:count personas evaluables asignadas',
            'areas' => ':count area activa|:count areas activas',
        ],

        'empty_title' => 'Todavia no hay sucursales',
        'empty_help' => 'Las sucursales son las sedes donde se levantan encuestas. Crea la primera para poder asignarle colaboradores y dispositivos.',
        'empty_search_title' => 'Ninguna sucursal coincide',
        'empty_search_help' => 'Prueba con otro nombre o codigo, o quita el filtro.',
    ],

    'organizations' => [
        'title' => 'Elige una organizacion',
        'subtitle' => 'Encuestas de satisfaccion',
        'help' => 'Tu cuenta pertenece a mas de una organizacion. Elige en cual quieres trabajar.',
        'submit' => 'Continuar',
    ],

    'errors' => [
        'summary' => 'Revisa lo siguiente antes de continuar',
    ],

    'placeholder' => [
        'not_built' => 'Este modulo todavia no esta construido.',
    ],

    'session' => [
        'logout' => 'Cerrar sesion',
    ],
];
