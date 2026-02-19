<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;

test('verifica la URL generada en el email de reset de contraseña', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com'
    ]);

    $token = 'test-token-123';
    $notification = new ResetPasswordNotification($token);

    // Obtener el email generado
    $mailMessage = $notification->toMail($user);

    // Verificar que use la configuración actual
    expect($mailMessage)->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class);

    // Crear un preview para ver la URL real
    $reflection = new \ReflectionClass($mailMessage);
    $viewDataProperty = $reflection->getProperty('viewData');
    $viewDataProperty->setAccessible(true);
    $viewData = $viewDataProperty->getValue($mailMessage);

    // Mostrar la URL generada
    if (isset($viewData['resetUrl'])) {
        echo "\n🔍 URL generada en el email: " . $viewData['resetUrl'] . "\n";
    expect($viewData['resetUrl'])->toContain(config('app.url'));
    } else {
        echo "\n⚠️  No se encontró resetUrl en viewData\n";
        echo "ViewData disponible: " . json_encode(array_keys($viewData)) . "\n";
    }

    // Verificar configuración actual
    echo "🔧 APP_URL: " . config('app.url') . "\n";
    // APP_FRONTEND_URL reference removed
    echo "🔧 MAIL_MAILER: " . config('mail.default') . "\n";
});
