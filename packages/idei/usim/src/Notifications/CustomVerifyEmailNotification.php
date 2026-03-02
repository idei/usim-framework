<?php

namespace Idei\Usim\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomVerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Base URL captured from the current HTTP request.
     * Stored at dispatch time so queued jobs use the correct port.
     */
    protected string $baseUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
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
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('✉️ Verifica tu dirección de email - ' . config('app.name'))
            ->view('emails.verify-email', [
                'user' => $notifiable,
                'verificationUrl' => $verificationUrl,
            ]);
    }

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable): string
    {
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        return "{$this->baseUrl}/auth/email-verified?id={$id}&hash={$hash}";
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
