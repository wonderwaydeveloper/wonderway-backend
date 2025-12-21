<?php

namespace App\Domain\Post\ValueObjects;

use InvalidArgumentException;

class PostContent
{
    private string $value;
    private const MAX_LENGTH = 280;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $value): void
    {
        if (empty(trim($value))) {
            throw new InvalidArgumentException('Post content cannot be empty');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Post content cannot exceed ' . self::MAX_LENGTH . ' characters');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLength(): int
    {
        return strlen($this->value);
    }

    public function containsHashtags(): bool
    {
        return preg_match('/#\w+/', $this->value) === 1;
    }

    public function containsMentions(): bool
    {
        return preg_match('/@\w+/', $this->value) === 1;
    }

    public function getHashtags(): array
    {
        preg_match_all('/#(\w+)/', $this->value, $matches);
        return $matches[1] ?? [];
    }

    public function getMentions(): array
    {
        preg_match_all('/@(\w+)/', $this->value, $matches);
        return $matches[1] ?? [];
    }
}