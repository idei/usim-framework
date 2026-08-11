<?php

namespace Idei\Usim\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;

        // Set queue name for email notifications
        $this->onQueue('emails');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     * Usando vista Blade completamente personalizada
     *
     * @param \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\CanResetPassword $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $expireConfig = config('auth.passwords.users.expire', 60);
        $expiresAt = now()->addMinutes($this->resolveExpireMinutes($expireConfig, 60));
        $resetUrl = URL::temporarySignedRoute('ui.catchall', $expiresAt, [
            'screen' => 'auth/reset-password',
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $appName = config('app.name');
        $appName = is_string($appName) ? $appName : 'Laravel';

        return (new MailMessage)
            ->subject('🔐 Restablecer Contraseña - ' . $appName)
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'token' => $this->token
            ]);
    }

    private function resolveExpireMinutes(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
