<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Open Graph (usado por WhatsApp, Facebook, LinkedIn) --}}
    <meta property="og:title" content="{{ ucfirst(str_replace('-', ' ', $screen)) }}" />
    <meta property="og:description" content="USIM — UI Services Implementation Model {{ $screen }}" />
    <meta property="og:image" content="{{ asset('vendor/idei/usim/images/default-image.png') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />

    {{-- Twitter Card (fallback adicional) --}}
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ asset('vendor/idei/usim/images/default-image.png') }}" />

    <title>{{ ucfirst(str_replace('-', ' ', $screen)) }}</title>
    @php
        $usimAssetVersion = static function (string $relativePath): int {
            $absolutePath = public_path($relativePath);
            return file_exists($absolutePath) ? (int) filemtime($absolutePath) : time();
        };
    @endphp
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/ui-theme-tokens.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/ui-theme-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/ui-components.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/ui-components.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/uploader-component.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/uploader-component.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/carousel-component.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/carousel-component.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/image-crop-editor.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/image-crop-editor.css') }}">
</head>
<body>
    <header id="top-menu-bar">
        <div id="menu"></div>
    </header>
    <main id="main"></main>
    <div id="modal-root"></div>
    {{-- <button id="reset-btn" onclick="location.href='?reset=true'">Reset</button> --}}

    @php
        // Obtener todos los parámetros de ruta automáticamente
        $routeParams = request()->route()->parameters();

        // Crear un array con prefijo "route_" para diferenciarlos de query params
        $prefixedRouteParams = [];
        foreach ($routeParams as $key => $value) {
            $prefixedRouteParams["route_$key"] = $value;
        }

        // Combinar con params manuales si existen (para compatibilidad)
        $allParams = array_merge($prefixedRouteParams, $params ?? []);
    @endphp

    <script>
        // Pass service name from Laravel to JavaScript
        window.SCREEN_NAME = '{{ $screen }}';
        window.RESET_STATE = {{ $reset ? 'true' : 'false' }};
        window.MENU_SERVICE = 'menu';
        window.PARAMS = @json($allParams);
        window.QUERY_PARAMS = new URLSearchParams(window.location.search);
    </script>
    <script src="{{ asset('vendor/idei/usim/js/ui-renderer.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/ui-renderer.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/image-crop-editor.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/image-crop-editor.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/uploader-component.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/uploader-component.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/calendar-component.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/calendar-component.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/carousel-component.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/carousel-component.js') }}"></script>
</body>
</html>
