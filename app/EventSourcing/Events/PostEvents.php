<?php

namespace App\EventSourcing\Events;

use App\EventSourcing\Event;

class PostCreatedEvent extends Event
{
    public function getEventType(): string
    {
        return 'post.created';
    }
}

class PostUpdatedEvent extends Event
{
    public function getEventType(): string
    {
        return 'post.updated';
    }
}

class PostLikedEvent extends Event
{
    public function getEventType(): string
    {
        return 'post.liked';
    }
}

class PostUnlikedEvent extends Event
{
    public function getEventType(): string
    {
        return 'post.unliked';
    }
}