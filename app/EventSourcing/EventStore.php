<?php

namespace App\EventSourcing;

use Illuminate\Support\Facades\DB;

class EventStore
{
    public function append(Event $event): void
    {
        DB::table('event_store')->insert([
            'aggregate_id' => $event->getAggregateId(),
            'event_type' => $event->getEventType(),
            'payload' => json_encode($event->getPayload()),
            'occurred_at' => $event->getOccurredAt(),
            'created_at' => now(),
        ]);
    }

    public function getEventsForAggregate(string $aggregateId): array
    {
        return DB::table('event_store')
            ->where('aggregate_id', $aggregateId)
            ->orderBy('occurred_at')
            ->get()
            ->toArray();
    }
}