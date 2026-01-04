<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Build the mail representation of the notification.
     *
     * @param mixed $notifiable Entity receiving the notification.
     *
     * @return MailMessage Mail message instance.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Vérifie ton adresse e-mail')
            ->greeting('Bienvenue sur KCDLE')
            ->line('Pour activer ton compte, merci de vérifier ton adresse e-mail en cliquant sur le bouton ci-dessous.')
            ->action('Vérifier mon adresse e-mail', $this->verificationUrl($notifiable))
            ->line('Si tu n\'es pas à l\'origine de cette création de compte, tu peux ignorer cet e-mail.');
    }

    /**
     * Generate the signed verification URL.
     *
     * @param mixed $notifiable Entity receiving the notification.
     *
     * @return string Signed verification URL.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
