<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('fortify.passwords').'.expire');
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage())
            ->subject('Set your password for '.config('app.name'))
            ->greeting("Hello {$notifiable->name},")
            ->line('An administrator has created an account for you on '.config('app.name').'.')
            ->line('Choose your password with the button below.')
            ->action('Set your password', $url)
            ->line("The link works for {$minutes} minutes. If it has expired, open the sign-in page and choose \"Forgot your password?\" to get a new one.");
    }
}
