<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountChangedMail extends Mailable
{
    /**
     * @param array<string, array{0: string, 1: string}> $changes label => [previous, new]
     */
    public function __construct(
        public readonly string $username,
        public readonly array $changes,
        public readonly bool $passwordChanged,
        public readonly string $changedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ' . config('app.name') . ' account was updated');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-changed');
    }
}