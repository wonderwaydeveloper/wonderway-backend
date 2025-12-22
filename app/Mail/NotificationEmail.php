<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $user;
    public $notification;

    public function __construct($user, $notification)
    {
        $this->user = $user;
        $this->notification = $notification;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'اعلان جدید از WonderWay',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: ['user' => $this->user, 'notification' => $this->notification],
        );
    }
}
