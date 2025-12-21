<?php

namespace App\CQRS\Queries;

class GetTimelineQuery implements QueryInterface
{
    public function __construct(
        private string $userId,
        private int $limit = 20,
        private ?string $cursor = null,
        private array $filters = []
    ) {}

    public function getCriteria(): array
    {
        return [
            'user_id' => $this->userId,
            'limit' => $this->limit,
            'cursor' => $this->cursor,
            'filters' => $this->filters,
        ];
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}