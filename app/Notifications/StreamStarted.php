<?php

namespace App\Notifications;

use App\Models\Stream;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StreamStarted extends Notification implements ShouldQueue
{
    use Queueable;

    private Stream $stream;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'stream_started',
            'stream_id' => $this->stream->id,
            'stream_title' => $this->stream->title,
            'streamer_name' => $this->stream->user->name,
            'streamer_username' => $this->stream->user->username,
            'started_at' => $this->stream->started_at,
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
