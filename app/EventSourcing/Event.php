<?php

namespace App\EventSourcing;

abstract class Event
{
    protected string $aggregateId;
    protected \DateTime $occurredAt;
    protected array $payload;

    public function __construct(string $aggregateId, array $payload = [])
    {
        $this->aggregateId = $aggregateId;
        $this->payload = $payload;
        $this->occurredAt = new \DateTime();
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getOccurredAt(): \DateTime
    {
        return $this->occurredAt;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    abstract public function getEventType(): string;
}