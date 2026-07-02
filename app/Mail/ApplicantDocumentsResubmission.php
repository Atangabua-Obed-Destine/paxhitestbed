<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an applicant when the admissions office flags one or more of their
 * uploaded documents as needing resubmission.
 */
class ApplicantDocumentsResubmission extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->from($this->data['from'], $this->data['sender'])
                    ->subject($this->data['subject'])
                    ->view('emails.applicant-documents-resubmission');
    }
}
