<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PersonContributionSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $sender;
    public array $person;
    public ?string $note;
    public ?string $mediaUrl;
    public ?string $mediaOriginalName;

    public function __construct(
        User $sender,
        array $person,
        ?string $note = null,
        ?string $mediaUrl = null,
        ?string $mediaOriginalName = null
    ) {
        $this->sender = $sender;
        $this->person = $person;
        $this->note = $note;
        $this->mediaUrl = $mediaUrl;
        $this->mediaOriginalName = $mediaOriginalName;
    }

    public function envelope(): Envelope
    {
        $personName = $this->person['name'] ?? $this->person['id'] ?? 'Person';
        $senderName = $this->sender->name ?: $this->sender->email;

        return new Envelope(
            subject: "[GEDCOM Archive] Contribution for {$personName} from {$senderName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.person_contribution',
        );
    }
}
