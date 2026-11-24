<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** A staff member's payslip, attached as a PDF. */
class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->from($this->data['from'], $this->data['sender'])
            ->subject($this->data['subject'])
            ->view('emails.payslip')
            ->attachData($this->data['pdf'], $this->data['filename'], ['mime' => 'application/pdf']);
    }
}
