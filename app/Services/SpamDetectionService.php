<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SpamDetectionService
{
    private $spamKeywords = [
        'spam', 'fake', 'scam', 'click here', 'free money', 'win now',
        'اسپم', 'جعلی', 'کلاهبرداری', 'اینجا کلیک', 'پول رایگان'
    ];

    private $suspiciousPatterns = [
        '/(.)\1{4,}/', // Repeated characters
        '/[A-Z]{5,}/', // Too many capitals
        '/\b\d{10,}\b/', // Long numbers (phone/card)
        '/https?:\/\/[^\s]+/', // URLs
    ];

    public function checkPost(Post $post): array
    {
        $score = 0;
        $reasons = [];

        // Content analysis
        $contentScore = $this->analyzeContent($post->content);
        $score += $contentScore['score'];
        $reasons = array_merge($reasons, $contentScore['reasons']);

        // User behavior analysis
        $userScore = $this->analyzeUserBehavior($post->user);
        $score += $userScore['score'];
        $reasons = array_merge($reasons, $userScore['reasons']);

        // Frequency analysis
        $frequencyScore = $this->analyzePostFrequency($post->user);
        $score += $frequencyScore['score'];
        $reasons = array_merge($reasons, $frequencyScore['reasons']);

        $isSpam = $score >= 70;

        if ($isSpam) {
            $this->handleSpamDetection($post, $score, $reasons);
        }

        return [
            'is_spam' => $isSpam,
            'score' => $score,
            'reasons' => $reasons
        ];
    }

    public function checkComment(Comment $comment): array
    {
        $score = 0;
        $reasons = [];

        // Content analysis
        $contentScore = $this->analyzeContent($comment->content);
        $score += $contentScore['score'];
        $reasons = array_merge($reasons, $contentScore['reasons']);

        // User behavior analysis
        $userScore = $this->analyzeUserBehavior($comment->user);
        $score += $userScore['score'];
        $reasons = array_merge($reasons, $userScore['reasons']);

        $isSpam = $score >= 60; // Lower threshold for comments

        if ($isSpam) {
            $this->handleSpamComment($comment, $score, $reasons);
        }

        return [
            'is_spam' => $isSpam,
            'score' => $score,
            'reasons' => $reasons
        ];
    }

    private function analyzeContent(string $content): array
    {
        $score = 0;
        $reasons = [];

        // Check for spam keywords
        foreach ($this->spamKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $score += 20;
                $reasons[] = "Contains spam keyword: {$keyword}";
            }
        }

        // Check for multiple URLs (more strict)
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/', $content);
        if ($urlCount >= 3) {
            $score += 50; // High penalty for multiple links
            $reasons[] = "Too many links detected ({$urlCount} links)";
        } elseif ($urlCount >= 2) {
            $score += 25;
            $reasons[] = "Multiple links detected";
        } elseif ($urlCount >= 1) {
            $score += 10;
            $reasons[] = "Contains URL";
        }

        // Check other suspicious patterns
        foreach ($this->suspiciousPatterns as $pattern) {
            if ($pattern !== '/https?:\/\/[^\s]+/' && preg_match($pattern, $content)) {
                $score += 15;
                $reasons[] = "Matches suspicious pattern";
            }
        }

        // Check content length
        if (strlen($content) < 10) {
            $score += 10;
            $reasons[] = "Content too short";
        }

        // Check for excessive emojis
        $emojiCount = preg_match_all('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]/u', $content);
        if ($emojiCount > 10) {
            $score += 15;
            $reasons[] = "Excessive emoji usage";
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    private function analyzeUserBehavior(User $user): array
    {
        $score = 0;
        $reasons = [];

        // New user check - handle null created_at
        if ($user->created_at && $user->created_at->diffInDays(now()) < 1) {
            $score += 20;
            $reasons[] = "Very new user account";
        }

        // Check user reputation
        $reportCount = \DB::table('reports')->where('reportable_type', 'user')
            ->where('reportable_id', $user->id)->count();
        if ($reportCount > 5) {
            $score += 25;
            $reasons[] = "User has multiple reports";
        }

        // Check if user is already flagged
        if (isset($user->is_flagged) && $user->is_flagged) {
            $score += 30;
            $reasons[] = "User is flagged";
        }

        // Check follower ratio
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        
        if ($following > 100 && $followers < 10) {
            $score += 15;
            $reasons[] = "Suspicious follower ratio";
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    private function analyzePostFrequency(User $user): array
    {
        $score = 0;
        $reasons = [];

        // Check posts in last hour
        $recentPosts = $user->posts()
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentPosts > 10) {
            $score += 30;
            $reasons[] = "Too many posts in short time";
        } elseif ($recentPosts > 5) {
            $score += 15;
            $reasons[] = "High posting frequency";
        }

        // Check duplicate content
        $lastPost = $user->posts()->latest()->first();
        if ($lastPost) {
            $similarPosts = $user->posts()
                ->where('content', 'like', '%' . substr($lastPost->content, 0, 50) . '%')
                ->where('id', '!=', $lastPost->id)
                ->count();

            if ($similarPosts > 0) {
                $score += 25;
                $reasons[] = "Duplicate or similar content detected";
            }
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    private function handleSpamDetection(Post $post, int $score, array $reasons): void
    {
        try {
            // Auto-flag high-score spam
            if ($score >= 90) {
                $post->update([
                    'is_flagged' => true,
                    'is_hidden' => true,
                    'flagged_at' => now()
                ]);
                
                Log::warning('Post auto-flagged as spam', [
                    'post_id' => $post->id,
                    'user_id' => $post->user_id,
                    'score' => $score,
                    'reasons' => $reasons
                ]);
            } else {
                // Just flag for review
                $post->update([
                    'is_flagged' => true,
                    'flagged_at' => now()
                ]);
            }

            // Create spam report
            \DB::table('spam_reports')->insert([
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'user_id' => $post->user_id,
                'spam_score' => $score,
                'detection_reasons' => json_encode($reasons),
                'auto_detected' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update user spam score
            $this->updateUserSpamScore($post->user, $score);

        } catch (\Exception $e) {
            Log::error('Error handling spam detection', [
                'post_id' => $post->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleSpamComment(Comment $comment, int $score, array $reasons): void
    {
        try {
            if ($score >= 80) {
                $comment->delete();
                
                Log::warning('Comment auto-deleted as spam', [
                    'comment_id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'score' => $score
                ]);
            }

            // Create spam report
            \DB::table('spam_reports')->insert([
                'reportable_type' => 'comment',
                'reportable_id' => $comment->id,
                'user_id' => $comment->user_id,
                'spam_score' => $score,
                'detection_reasons' => json_encode($reasons),
                'auto_detected' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling spam comment', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function updateUserSpamScore(User $user, int $spamScore): void
    {
        $cacheKey = "user_spam_score_{$user->id}";
        $currentScore = Cache::get($cacheKey, 0);
        $newScore = $currentScore + ($spamScore / 10);

        Cache::put($cacheKey, $newScore, now()->addDays(7));

        // Auto-suspend user if spam score is too high
        if ($newScore >= 50) {
            $user->update([
                'is_suspended' => true,
                'suspended_until' => now()->addDays(3)
            ]);

            Log::warning('User auto-suspended for spam', [
                'user_id' => $user->id,
                'spam_score' => $newScore
            ]);
        }
    }

    public function getUserSpamScore(User $user): int
    {
        return Cache::get("user_spam_score_{$user->id}", 0);
    }

    public function isUserSuspicious(User $user): bool
    {
        return $this->getUserSpamScore($user) >= 30;
    }
}