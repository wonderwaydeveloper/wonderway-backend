<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\Post;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    
    return $conversation && (
        $conversation->user_one_id === $user->id || 
        $conversation->user_two_id === $user->id
    );
});

Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('online-users', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar,
    ];
});

Broadcast::channel('timeline', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'username' => $user->username,
        'avatar' => $user->avatar,
    ];
});

Broadcast::channel('user.timeline.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('post.{postId}', function ($user, $postId) {
    // Allow access if user can see the post
    $post = Post::find($postId);
    return $post && (
        !$post->user->is_private || 
        $post->user_id === $user->id ||
        $user->isFollowing($post->user_id)
    );
});
