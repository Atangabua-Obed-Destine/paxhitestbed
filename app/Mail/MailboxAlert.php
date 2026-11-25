<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** "You have a new message from X" — the alert only, never the message. */
class MailboxAlert extends Mailable
{
    public function __construct(
        public string $sender,
        public string $topic,
        public string $link,
        public ?string $recipientName,
        public string $fromEmail,
        public string $fromName,
    ) {
    }

    public function build()
    {
        return $this->from($this->fromEmail, $this->fromName)
            ->subject(__('New message from :name: :subject', ['name' => $this->sender, 'subject' => $this->topic]))
            ->view('emails.mailbox-alert');
    }
}
