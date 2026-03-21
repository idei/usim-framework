<?php

use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\TermsController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('usim-landing');
})->name('landing');

if (config('app.env') === 'local') {
    // Rutas para el visor de logs - Solo en entorno local
    Route::prefix('logs')->group(function () {
        Route::get('/', [LogViewerController::class, 'index'])->name('logs.index');
        Route::get('/content', [LogViewerController::class, 'content'])->name('logs.content');
        Route::get('/download', [LogViewerController::class, 'download'])->name('logs.download');
        Route::post('/clear', [LogViewerController::class, 'clear'])->name('logs.clear');
    });
}

// Dynamic UI Screen Catcher (Catch-All)
// Allows URLs like /admin/dashboard to resolve to Admin\Dashboard screen class
// Now also handles the root path '/' defaulting to 'landing'
// Must be the LAST route definition to not intercept other specific routes
Route::get('/{screen?}', function (?string $screen = 'home') {
    if ($screen === 'favicon.ico')
        return abort(404);

    $reset = request()->query('reset', false);
    return view('usim::app', [
        'screen' => $screen,
        'reset' => $reset
    ]);
})->where('screen', '^(?!api|docs|terms|vendor|storage|css|js|images|telescope|_debugbar).*$')->name('ui.catchall');

// Terms of Service Route
Route::get('/terms', TermsController::class)->name('terms');

// Documentation Routes
Route::prefix('docs')->group(function () {
    // Índice principal de documentación
    Route::get('/', [DocumentationController::class, 'docsIndex'])->name('docs.index');

    // Documentación principal (3 archivos)
    Route::get('/api-complete', [DocumentationController::class, 'apiCompleteDocs'])->name('docs.api-complete');
    Route::get('/implementation-summary', [DocumentationController::class, 'implementationSummaryDocs'])->name('docs.implementation-summary');
    Route::get('/technical-components', [DocumentationController::class, 'technicalComponentsDocs'])->name('docs.technical-components');

    // Documentación especializada (2 archivos)
    Route::get('/email-customization', [DocumentationController::class, 'emailCustomizationDocs'])->name('docs.email-customization');
    Route::get('/file-upload-examples', [DocumentationController::class, 'fileUploadExamplesDocs'])->name('docs.file-upload-examples');

    // Rutas de compatibilidad con enlaces antiguos (redirects)
    Route::get('/api-client', fn() => redirect()->route('docs.api-complete'))->name('docs.api-client.redirect');
    Route::get('/css-structure', fn() => redirect()->route('docs.technical-components'))->name('docs.css-structure.redirect');
});
