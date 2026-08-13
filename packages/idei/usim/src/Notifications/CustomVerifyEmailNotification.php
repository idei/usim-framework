<?php

namespace Idei\Usim\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
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
     *
     * @param \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\MustVerifyEmail $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $appName = config('app.name');
        $appName = is_string($appName) ? $appName : 'Laravel';

        return (new MailMessage)
            ->subject('✉️ Verifica tu dirección de email - ' . $appName)
            ->view('emails.verify-email', [
                'user' => $notifiable,
                'verificationUrl' => $verificationUrl,
            ]);
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\MustVerifyEmail $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());
        $expireConfig = config('auth.verification.expire', 60);
        $expiresAt = now()->addMinutes($this->resolveExpireMinutes($expireConfig, 60));

        return URL::temporarySignedRoute('ui.catchall', $expiresAt, [
            'screen' => 'auth/email-verified',
            'id' => $id,
            'hash' => $hash,
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
