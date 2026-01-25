<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset notification.
 *
 * This notification generates a frontend URL so the SPA can render
 * the reset password page while relying on the backend API to actually
 * perform the reset.
 */
class ResetPasswordNotification extends ResetPassword
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
        $expire = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage())
            ->subject('Réinitialisation de ton mot de passe')
            ->greeting('Réinitialisation du mot de passe')
            ->line('Tu reçois cet e-mail car une demande de réinitialisation de mot de passe a été effectuée pour ton compte.')
            ->action('Réinitialiser mon mot de passe', $this->resetUrl($notifiable))
            ->line("Ce lien expirera dans {$expire} minutes.")
            ->line('Si tu n\'es pas à l\'origine de cette demande, tu peux ignorer cet e-mail.');
    }

    /**
     * Generate the reset URL to the frontend application.
     *
     * @param mixed $notifiable Entity receiving the notification.
     *
     * @return string Reset URL.
     */
    protected function resetUrl($notifiable): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $email = rawurlencode((string) $notifiable->getEmailForPasswordReset());
        $token = rawurlencode($this->token);

        return $base . '/reset-password?token=' . $token . '&email=' . $email;
    }
}
