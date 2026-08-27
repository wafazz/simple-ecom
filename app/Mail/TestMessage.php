<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Proves the mail transport works.
 *
 * A Mailable rather than Mail::raw() because MailFake::raw() is a no-op — a
 * raw send records nothing and cannot be asserted, so the test button would be
 * covered by a test that could never fail.
 */
class TestMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $storeName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test message from '.$this->storeName);
    }

    /** Plain text: this exists to prove delivery, not to be looked at. */
    public function content(): Content
    {
        return new Content(text: 'mail.test');
    }
}
