<?php

// @usim: feature="core", type="lang"

return [
    'root' => [
        'description' => 'Usuario con acceso total e incondicional a todas las funciones.',
        'name' => 'Raíz',
    ],
    'admin' => [
        'description' => 'Gestiona el sistema y los usuarios.',
        'name' => 'Administrador',
    ],
    'registered' => [
        'description' => 'Usuario con acceso básico al sistema.',
        'name' => 'Usuario Registrado',
        'waiting' => 'En espera (Registrado)',
    ],
    'approved' => [
        'description' => 'Usuario aprobado con acceso a funciones adicionales.',
        'name' => 'Aprobado',
    ],
    'translator' => [
        'description' => 'Usuario responsable de gestionar las traducciones.',
        'name' => 'Traductor',
    ],
    'developer' => [
        'description' => 'Usuario con acceso a herramientas de desarrollo y depuración.',
        'name' => 'Programador',
    ],
    'user' => [
        'description' => 'Usuario con acceso básico al sistema.',
        'name' => 'Usuario',
    ],
    'guest' => [
        'name' => 'Invitado',
        'description' => 'Usuario con acceso básico al sistema.',
    ],
    'none' => 'Ninguno',
];
