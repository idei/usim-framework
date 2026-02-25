<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UI Screens Namespace
    |--------------------------------------------------------------------------
    |
    | Namespace base donde se buscan los servicios de pantallas (screens).
    */
    'screens_namespace' => 'App\\UI\\Screens',

    /*
    |--------------------------------------------------------------------------
    | UI Screens Path
    |--------------------------------------------------------------------------
    |
    | Ruta absoluta donde se encuentran los archivos de las pantallas.
    | Por defecto: app_path('UI/Screens')
    */
    'screens_path' => app_path('UI/Screens'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | La URL base para las peticiones HTTP internas hacia la API.
    | Si no se define, utilizará la URL principal de la aplicación (APP_URL).
    | Útil cuando la API está en un servidor o contenedor diferente.
    */
    'api_url' => env('API_BASE_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Registered UI Services
    |--------------------------------------------------------------------------
    */
    // Los servicios se registrarán aquí, actualmente se merging con el config del app
];
