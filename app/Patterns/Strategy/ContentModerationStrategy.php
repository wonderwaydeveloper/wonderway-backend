<?php

namespace App\Patterns\Strategy;

interface ContentModerationStrategy
{
    public function moderate(string $content): ModerationResult;
}

class ModerationResult
{
    public function __construct(
        private bool $isApproved,
        private array $violations = [],
        private int $confidenceScore = 0,
        private array $suggestedActions = []
    ) {}

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    public function getConfidenceScore(): int
    {
        return $this->confidenceScore;
    }

    public function getSuggestedActions(): array
    {
        return $this->suggestedActions;
    }
}

class SpamDetectionStrategy implements ContentModerationStrategy
{
    public function moderate(string $content): ModerationResult
    {
        $violations = [];
        $score = 100;

        // Check for spam patterns
        if (preg_match_all('/https?:\/\/[^\s]+/', $content) > 2) {
            $violations[] = 'Too many links';
            $score -= 30;
        }

        if (preg_match('/(.)\1{4,}/', $content)) {
            $violations[] = 'Repeated characters';
            $score -= 20;
        }

        if (str_word_count($content) < 3 && strlen($content) > 50) {
            $violations[] = 'Suspicious character pattern';
            $score -= 25;
        }

        return new ModerationResult(
            $score >= 70,
            $violations,
            $score,
            $score < 70 ? ['review', 'flag'] : []
        );
    }
}

class ProfanityFilterStrategy implements ContentModerationStrategy
{
    private array $profanityWords = ['spam', 'scam', 'fake', 'bot'];

    public function moderate(string $content): ModerationResult
    {
        $violations = [];
        $lowerContent = strtolower($content);
        
        foreach ($this->profanityWords as $word) {
            if (strpos($lowerContent, $word) !== false) {
                $violations[] = "Contains inappropriate word: {$word}";
            }
        }

        $score = count($violations) > 0 ? 40 : 100;

        return new ModerationResult(
            count($violations) === 0,
            $violations,
            $score,
            count($violations) > 0 ? ['censor', 'warn_user'] : []
        );
    }
}

class ContentModerationContext
{
    private ContentModerationStrategy $strategy;

    public function setStrategy(ContentModerationStrategy $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function moderate(string $content): ModerationResult
    {
        return $this->strategy->moderate($content);
    }

    public function moderateWithMultipleStrategies(string $content, array $strategies): array
    {
        $results = [];
        foreach ($strategies as $strategy) {
            $this->setStrategy($strategy);
            $results[] = $this->moderate($content);
        }
        return $results;
    }
}