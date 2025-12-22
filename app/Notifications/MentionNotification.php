<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $mentioner;
    protected $mentionable;

    public function __construct($mentioner, $mentionable)
    {
        $this->mentioner = $mentioner;
        $this->mentionable = $mentionable;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $type = class_basename($this->mentionable);

        return (new MailMessage())
            ->subject('You were mentioned by ' . $this->mentioner->name)
            ->line($this->mentioner->name . ' mentioned you in a ' . strtolower($type))
            ->line('"' . substr($this->mentionable->content, 0, 100) . '..."')
            ->action('View ' . $type, url('/posts/' . ($type === 'Post' ? $this->mentionable->id : $this->mentionable->post_id)));
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'mention',
            'mentioner_id' => $this->mentioner->id,
            'mentioner_name' => $this->mentioner->name,
            'mentioner_username' => $this->mentioner->username,
            'mentioner_avatar' => $this->mentioner->avatar,
            'mentionable_type' => class_basename($this->mentionable),
            'mentionable_id' => $this->mentionable->id,
            'content_preview' => substr($this->mentionable->content, 0, 100),
            'post_id' => $this->mentionable instanceof \App\Models\Post
                ? $this->mentionable->id
                : $this->mentionable->post_id,
        ];
    }
}
