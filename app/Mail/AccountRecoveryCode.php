<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountRecoveryCode extends Mailable
{
    public function __construct(public string $code, public string $applicationName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->applicationName.' — Account recovery OTP');
    }

    public function content(): Content
    {
        return new Content(text: 'recovery.otp-email');
    }
}
