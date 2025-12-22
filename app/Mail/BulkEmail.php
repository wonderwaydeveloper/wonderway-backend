<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $user;
    public $subject;
    public $view;
    public $data;

    public function __construct($user, $subject, $view, $data = [])
    {
        $this->user = $user;
        $this->subject = $subject;
        $this->view = $view;
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->view,
            with: array_merge(['user' => $this->user], $this->data),
        );
    }
}
