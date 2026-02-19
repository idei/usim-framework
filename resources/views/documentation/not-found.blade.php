<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - No encontrado</title>

    <!-- CSS Unificado para tema oscuro de documentación -->
    <link rel="stylesheet" href="css/documentation-dark-theme.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ {{ $title }} - No encontrado</h1>
        </div>
        <div class="content">
            <div class="error-icon">📄</div>
            <div class="error-message">El archivo de documentación solicitado no se ha encontrado.</div>

            @if(isset($file))
                <div class="file-name">{{ $file }}</div>
            @endif

            <p>El archivo de documentación que intentas acceder no existe o no se pudo leer.</p>

            <div class="actions">
                <a href="/api-client" class="button button-primary">← Volver al API Client</a>
                <a href="/docs" class="button button-secondary">📚 Ver documentación</a>
            </div>
        </div>
    </div>
</body>
</html>
