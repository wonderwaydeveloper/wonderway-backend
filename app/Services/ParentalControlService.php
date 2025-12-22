<?php

namespace App\Services;

use App\Models\ParentalControl;

class ParentalControlService
{
    public function createControl($parentId, $childId, $settings = [])
    {
        return ParentalControl::create([
            'parent_id' => $parentId,
            'child_id' => $childId,
            'content_filter_enabled' => $settings['content_filter_enabled'] ?? true,
            'screen_time_limit' => $settings['screen_time_limit'] ?? 120,
            'blocked_keywords' => $settings['blocked_keywords'] ?? [],
            'blocked_users' => $settings['blocked_users'] ?? [],
            'allowed_only_mode' => $settings['allowed_only_mode'] ?? false,
        ]);
    }

    public function updateSettings($controlId, $settings)
    {
        $control = ParentalControl::findOrFail($controlId);
        $control->update($settings);

        return $control;
    }

    public function canViewContent($userId, $postId)
    {
        $control = ParentalControl::where('child_id', $userId)->first();

        if (! $control || ! $control->content_filter_enabled) {
            return true;
        }

        $post = \App\Models\Post::find($postId);

        if (! $post) {
            return false;
        }

        // Check blocked keywords
        if ($this->containsBlockedKeywords($post->content, $control->blocked_keywords)) {
            return false;
        }

        // Check blocked users
        if (in_array($post->user_id, $control->blocked_users)) {
            return false;
        }

        // Check allowed only mode
        if ($control->allowed_only_mode && ! in_array($post->user_id, $control->allowed_users)) {
            return false;
        }

        return true;
    }

    public function blockContent($controlId, $keyword)
    {
        $control = ParentalControl::findOrFail($controlId);
        $keywords = $control->blocked_keywords ?? [];

        if (! in_array($keyword, $keywords)) {
            $keywords[] = $keyword;
            $control->update(['blocked_keywords' => $keywords]);
        }

        return $control;
    }

    public function blockUser($controlId, $userId)
    {
        $control = ParentalControl::findOrFail($controlId);
        $blockedUsers = $control->blocked_users ?? [];

        if (! in_array($userId, $blockedUsers)) {
            $blockedUsers[] = $userId;
            $control->update(['blocked_users' => $blockedUsers]);
        }

        return $control;
    }

    public function getChildActivity($childId)
    {
        return [
            'posts_created' => \App\Models\Post::where('user_id', $childId)->count(),
            'comments_made' => \App\Models\Comment::where('user_id', $childId)->count(),
            'followers' => \App\Models\Follow::where('following_id', $childId)->count(),
            'following' => \App\Models\Follow::where('follower_id', $childId)->count(),
            'screen_time_today' => $this->getScreenTimeToday($childId),
            'last_active' => \App\Models\User::find($childId)->last_active_at,
        ];
    }

    private function getScreenTimeToday($userId)
    {
        $logs = \App\Models\ActivityLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->get();

        $totalTime = 0;
        foreach ($logs as $log) {
            $totalTime += $log->duration ?? 0;
        }

        return $totalTime;
    }

    private function containsBlockedKeywords($content, $keywords)
    {
        if (! $keywords) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
