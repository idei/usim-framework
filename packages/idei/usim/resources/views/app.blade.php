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
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/components/uploader/index.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/components/uploader/index.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/components/carousel/index.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/components/carousel/index.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/components/image-crop-editor/index.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/components/image-crop-editor/index.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/components/textarea/index.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/components/textarea/index.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/idei/usim/css/components/split/index.css') }}?v={{ $usimAssetVersion('vendor/idei/usim/css/components/split/index.css') }}">
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
    <script src="{{ asset('vendor/idei/usim/js/component-registry.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/component-registry.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/ui-renderer.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/ui-renderer.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/shared/ui-event.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/shared/ui-event.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/shared/content-render.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/shared/content-render.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/container/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/container/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/label/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/label/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/button/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/button/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/input/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/input/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/select/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/select/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/checkbox/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/checkbox/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/storage/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/storage/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/card/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/card/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/menudropdown/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/menudropdown/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/table/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/table/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/tableheaderrow/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/tableheaderrow/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/tablerow/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/tablerow/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/tablecell/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/tablecell/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/tableheadercell/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/tableheadercell/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/image-crop-editor/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/image-crop-editor/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/uploader/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/uploader/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/calendar/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/calendar/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/carousel/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/carousel/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/lib/marked.min.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/lib/marked.min.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/textarea/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/textarea/index.js') }}"></script>
    <script src="{{ asset('vendor/idei/usim/js/components/split/index.js') }}?v={{ $usimAssetVersion('vendor/idei/usim/js/components/split/index.js') }}"></script>
</body>
</html>
