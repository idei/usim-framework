<?php

namespace Idei\Usim\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public $token;

    /**
     * Base URL captured from the current HTTP request.
     * Stored at dispatch time so queued jobs use the correct port.
     */
    protected string $baseUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;

        // Capture the real base URL from the current request (includes correct port)
        $this->baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

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
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->baseUrl
            . '/auth/reset-password?token='
            . $this->token
            . '&email='
            . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('🔐 Restablecer Contraseña - ' . config('app.name'))
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'token' => $this->token
            ]);
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
