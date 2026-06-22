<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AcceptanceLetter extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message — acceptance letter email with the PDF attached.
     */
    public function build()
    {
        $mail = $this->from($this->data['from'], $this->data['sender'])
                     ->subject($this->data['subject'])
                     ->view('emails.acceptance-letter');

        if (!empty($this->data['pdf'])) {
            $mail->attachData($this->data['pdf'], 'Acceptance-Letter.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
