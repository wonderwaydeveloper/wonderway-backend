<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $user;
    public $code;

    public function __construct($user, $code)
    {
        $this->user = $user;
        $this->code = $code;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تایید ایمیل WonderWay',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification',
            with: ['user' => $this->user, 'code' => $this->code],
        );
    }
}
