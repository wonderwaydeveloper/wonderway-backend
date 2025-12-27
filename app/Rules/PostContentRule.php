<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PostContentRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen(trim($value)) < 1) {
            $fail('Post cannot be empty.');
            return;
        }

        if (strlen($value) > 280) {
            $fail('Post cannot exceed 280 characters.');
            return;
        }

        $linkCount = preg_match_all('/https?:\/\/[^\s]+/', $value);
        if ($linkCount > 2) {
            $fail('Post cannot contain more than 2 links.');
            return;
        }

        $mentionCount = preg_match_all('/@[a-zA-Z0-9_]+/', $value);
        if ($mentionCount > 5) {
            $fail('Post cannot contain more than 5 mentions.');
            return;
        }

        if ($this->containsSpamPatterns($value)) {
            $fail('Post content is suspected to be spam.');
            return;
        }
    }

    private function containsSpamPatterns(string $content): bool
    {
        $spamPatterns = [
            '/(.)\1{10,}/',
            '/\b(buy|sale|discount|offer|free|win|prize)\b.*\b(now|today|click|link)\b/i',
            '/\b(follow|like|subscribe)\b.*\b(back|return|exchange)\b/i',
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}