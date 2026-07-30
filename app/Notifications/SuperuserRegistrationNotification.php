<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuperuserRegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(public User $newUser)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New User Pending Verification: ' . $this->newUser->name)
            ->greeting('Hello Superuser,')
            ->line("A new user has registered on the platform and is waiting for your verification.")
            ->line("Name: {$this->newUser->name}")
            ->line("Email: {$this->newUser->email}")
            ->action('Review User in Admin Panel', url('/admin/users'))
            ->line('Thank you!');
    }
}
