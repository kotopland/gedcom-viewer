<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    use Queueable;

    public string $url;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $url)
    {
        $this->url = $url;
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
        return (new MailMessage)
            ->subject('Your Magic Login Link - Topland Family Archive')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Click the button below to log in to the Topland Family Genealogy Archive without entering a password.')
            ->action('Log In to Family Archive', $this->url)
            ->line('This magic login link will expire in 15 minutes for your security.')
            ->line('If you did not request this login link, no further action is required.');
    }
}
