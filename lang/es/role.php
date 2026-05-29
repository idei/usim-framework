<?php

return [
    'root' => [
        'name' => 'Root',
        'description' => 'Usuario con acceso total e incondicional a todas las funciones.',
    ],
    'admin' => [
        'name' => 'Administrator',
        'description' => 'Gestiona el sistema y los usuarios.',
    ],
    'registered' => [
        'name' => 'Registered',
        'description' => 'Usuario registrado con permisos limitados.',
    ],
    'approved' => [
        'name' => 'Approved',
        'description' => 'Usuario aprobado con acceso a funciones adicionales.',
    ],
    'translator' => [
        'name' => 'Translator',
        'description' => 'Usuario responsable de gestionar las traducciones.',
    ],
    'developer' => [
        'name' => 'Developer',
        'description' => 'Usuario con acceso a herramientas de desarrollo y depuración.',
    ],
];
