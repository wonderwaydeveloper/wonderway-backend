<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,60');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,5');

Route::prefix('auth/phone')->group(function () {
    Route::post('/send-code', [PhoneAuthController::class, 'sendCode']);
    Route::post('/verify', [PhoneAuthController::class, 'verifyCode']);
    Route::post('/register', [PhoneAuthController::class, 'register']);
    Route::post('/login', [PhoneAuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('auth/2fa')->group(function () {
    Route::post('/enable', [App\Http\Controllers\Api\TwoFactorController::class, 'enable']);
    Route::post('/verify', [App\Http\Controllers\Api\TwoFactorController::class, 'verify']);
    Route::post('/disable', [App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
    Route::get('/backup-codes', [App\Http\Controllers\Api\TwoFactorController::class, 'backupCodes']);
});

Route::prefix('auth/password')->group(function () {
    Route::post('/forgot', [App\Http\Controllers\Api\PasswordResetController::class, 'forgot']);
    Route::post('/reset', [App\Http\Controllers\Api\PasswordResetController::class, 'reset']);
    Route::post('/verify-token', [App\Http\Controllers\Api\PasswordResetController::class, 'verifyToken']);
});

Route::prefix('auth/social')->group(function () {
    Route::get('/google', [App\Http\Controllers\Api\SocialAuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [App\Http\Controllers\Api\SocialAuthController::class, 'handleGoogleCallback']);
    Route::get('/github', [App\Http\Controllers\Api\SocialAuthController::class, 'redirectToGithub']);
    Route::get('/github/callback', [App\Http\Controllers\Api\SocialAuthController::class, 'handleGithubCallback']);
    Route::get('/facebook', [App\Http\Controllers\Api\SocialAuthController::class, 'redirectToFacebook']);
    Route::get('/facebook/callback', [App\Http\Controllers\Api\SocialAuthController::class, 'handleFacebookCallback']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('posts', PostController::class)->except(['update'])->middleware('throttle:10,1');
    Route::post('/posts/{post}/like', [PostController::class, 'like'])->middleware('throttle:60,1');
    Route::get('/timeline', [PostController::class, 'timeline']);
    Route::get('/drafts', [PostController::class, 'drafts']);
    Route::post('/posts/{post}/publish', [PostController::class, 'publish']);

    Route::post('/threads', [App\Http\Controllers\Api\ThreadController::class, 'create']);
    Route::get('/threads/{post}', [App\Http\Controllers\Api\ThreadController::class, 'show']);

    Route::post('/scheduled-posts', [App\Http\Controllers\Api\ScheduledPostController::class, 'store']);
    Route::get('/scheduled-posts', [App\Http\Controllers\Api\ScheduledPostController::class, 'index']);
    Route::delete('/scheduled-posts/{scheduledPost}', [App\Http\Controllers\Api\ScheduledPostController::class, 'destroy']);

    Route::get('/gifs/search', [App\Http\Controllers\Api\GifController::class, 'search']);
    Route::get('/gifs/trending', [App\Http\Controllers\Api\GifController::class, 'trending']);

    Route::get('/bookmarks', [App\Http\Controllers\Api\BookmarkController::class, 'index']);
    Route::post('/posts/{post}/bookmark', [App\Http\Controllers\Api\BookmarkController::class, 'toggle']);

    Route::post('/posts/{post}/repost', [App\Http\Controllers\Api\RepostController::class, 'repost']);
    Route::get('/my-reposts', [App\Http\Controllers\Api\RepostController::class, 'myReposts']);

    Route::get('/stories', [App\Http\Controllers\Api\StoryController::class, 'index']);
    Route::post('/stories', [App\Http\Controllers\Api\StoryController::class, 'store']);
    Route::delete('/stories/{story}', [App\Http\Controllers\Api\StoryController::class, 'destroy']);
    Route::post('/stories/{story}/view', [App\Http\Controllers\Api\StoryController::class, 'view']);

    Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])
        ->middleware('check.reply.permission');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('/comments/{comment}/like', [CommentController::class, 'like']);

    Route::post('/users/{user}/follow', [FollowController::class, 'follow'])->middleware('throttle:30,1');
    Route::post('/users/{user}/follow-request', [App\Http\Controllers\Api\FollowRequestController::class, 'send']);
    Route::get('/follow-requests', [App\Http\Controllers\Api\FollowRequestController::class, 'index']);
    Route::post('/follow-requests/{followRequest}/accept', [App\Http\Controllers\Api\FollowRequestController::class, 'accept']);
    Route::post('/follow-requests/{followRequest}/reject', [App\Http\Controllers\Api\FollowRequestController::class, 'reject']);
    Route::get('/users/{user}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{user}/following', [FollowController::class, 'following']);

    Route::get('/users/{user}', [ProfileController::class, 'show']);
    Route::get('/users/{user}/posts', [ProfileController::class, 'posts']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/privacy', [ProfileController::class, 'updatePrivacy']);
    Route::get('/search/users', [App\Http\Controllers\Api\SearchController::class, 'users']);
    Route::get('/search/posts', [App\Http\Controllers\Api\SearchController::class, 'posts']);
    Route::get('/search/all', [App\Http\Controllers\Api\SearchController::class, 'all']);
    Route::get('/suggestions/users', [App\Http\Controllers\Api\SuggestionController::class, 'users']);

    Route::post('/devices/register', [App\Http\Controllers\Api\DeviceController::class, 'register']);
    Route::delete('/devices/{token}', [App\Http\Controllers\Api\DeviceController::class, 'unregister']);

    Route::prefix('groups')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\GroupChatController::class, 'create']);
        Route::get('/my-groups', [App\Http\Controllers\Api\GroupChatController::class, 'myGroups']);
        Route::post('/{group}/members', [App\Http\Controllers\Api\GroupChatController::class, 'addMember']);
        Route::delete('/{group}/members/{userId}', [App\Http\Controllers\Api\GroupChatController::class, 'removeMember']);
        Route::put('/{group}', [App\Http\Controllers\Api\GroupChatController::class, 'update']);
        Route::post('/{group}/messages', [App\Http\Controllers\Api\GroupChatController::class, 'sendMessage']);
        Route::get('/{group}/messages', [App\Http\Controllers\Api\GroupChatController::class, 'messages']);
    });

    Route::prefix('messages')->group(function () {
        Route::get('/conversations', [App\Http\Controllers\Api\MessageController::class, 'conversations']);
        Route::get('/users/{user}', [App\Http\Controllers\Api\MessageController::class, 'messages']);
        Route::post('/users/{user}', [App\Http\Controllers\Api\MessageController::class, 'send'])->middleware('throttle:60,1');
        Route::post('/users/{user}/typing', [App\Http\Controllers\Api\MessageController::class, 'typing']);
        Route::post('/{message}/read', [App\Http\Controllers\Api\MessageController::class, 'markAsRead']);
        Route::get('/unread-count', [App\Http\Controllers\Api\MessageController::class, 'unreadCount']);
    });

    Route::get('/subscription/plans', [App\Http\Controllers\Api\SubscriptionController::class, 'plans']);
    Route::get('/subscription/current', [App\Http\Controllers\Api\SubscriptionController::class, 'current']);
    Route::post('/subscription/subscribe', [App\Http\Controllers\Api\SubscriptionController::class, 'subscribe']);
    Route::post('/subscription/cancel', [App\Http\Controllers\Api\SubscriptionController::class, 'cancel']);
    Route::get('/subscription/history', [App\Http\Controllers\Api\SubscriptionController::class, 'history']);

    Route::get('/hashtags/trending', [App\Http\Controllers\Api\HashtagController::class, 'trending']);
    Route::get('/hashtags/search', [App\Http\Controllers\Api\HashtagController::class, 'search']);
    Route::get('/hashtags/{hashtag:slug}', [App\Http\Controllers\Api\HashtagController::class, 'show']);

    // Poll routes
    Route::post('/polls', [App\Http\Controllers\Api\PollController::class, 'store']);
    Route::post('/polls/{poll}/vote/{option}', [App\Http\Controllers\Api\PollController::class, 'vote']);
    Route::get('/polls/{poll}/results', [App\Http\Controllers\Api\PollController::class, 'results']);

    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/unread', [App\Http\Controllers\Api\NotificationController::class, 'unread']);
        Route::get('/unread-count', [App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    });

    Route::prefix('parental')->group(function () {
        Route::post('/link-child', [App\Http\Controllers\Api\ParentalControlController::class, 'linkChild']);
        Route::post('/links/{link}/approve', [App\Http\Controllers\Api\ParentalControlController::class, 'approveLink']);
        Route::post('/links/{link}/reject', [App\Http\Controllers\Api\ParentalControlController::class, 'rejectLink']);
        Route::get('/settings', [App\Http\Controllers\Api\ParentalControlController::class, 'getSettings']);
        Route::put('/children/{child}/settings', [App\Http\Controllers\Api\ParentalControlController::class, 'updateSettings']);
        Route::get('/children', [App\Http\Controllers\Api\ParentalControlController::class, 'getChildren']);
        Route::get('/parents', [App\Http\Controllers\Api\ParentalControlController::class, 'getParents']);
        Route::get('/child/{child}/activity', [App\Http\Controllers\Api\ParentalControlController::class, 'childActivity']);
        Route::post('/child/{child}/block-content', [App\Http\Controllers\Api\ParentalControlController::class, 'blockContent']);
    });

    // Performance & Monitoring
    Route::prefix('performance')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\PerformanceController::class, 'dashboard']);
        Route::get('/timeline/optimized', [App\Http\Controllers\Api\PerformanceController::class, 'optimizeTimeline']);
        Route::post('/cache/warmup', [App\Http\Controllers\Api\PerformanceController::class, 'warmupCache']);
        Route::delete('/cache/clear', [App\Http\Controllers\Api\PerformanceController::class, 'clearCache']);
    });

    // Monitoring routes (add admin middleware in production)
    Route::prefix('monitoring')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\MonitoringController::class, 'dashboard']);
        Route::get('/database', [App\Http\Controllers\Api\MonitoringController::class, 'database']);
        Route::get('/cache', [App\Http\Controllers\Api\MonitoringController::class, 'cache']);
        Route::get('/queue', [App\Http\Controllers\Api\MonitoringController::class, 'queue']);
    });

    Route::post('/users/{user}/block', [App\Http\Controllers\Api\ProfileController::class, 'block']);
    Route::post('/users/{user}/unblock', [App\Http\Controllers\Api\ProfileController::class, 'unblock']);
    Route::post('/users/{user}/mute', [App\Http\Controllers\Api\ProfileController::class, 'mute']);
    Route::post('/users/{user}/unmute', [App\Http\Controllers\Api\ProfileController::class, 'unmute']);

    // Phase 3: Notification Preferences
    Route::prefix('notifications/preferences')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\NotificationPreferenceController::class, 'index']);
        Route::put('/', [App\Http\Controllers\Api\NotificationPreferenceController::class, 'update']);
        Route::put('/{type}', [App\Http\Controllers\Api\NotificationPreferenceController::class, 'updateType']);
        Route::put('/{type}/{category}', [App\Http\Controllers\Api\NotificationPreferenceController::class, 'updateSpecific']);
    });

    // Phase 3: Media Upload
    Route::prefix('media')->group(function () {
        Route::post('/upload/image', [App\Http\Controllers\Api\MediaController::class, 'uploadImage']);
        Route::post('/upload/video', [App\Http\Controllers\Api\MediaController::class, 'uploadVideo']);
        Route::post('/upload/document', [App\Http\Controllers\Api\MediaController::class, 'uploadDocument']);
        Route::delete('/delete', [App\Http\Controllers\Api\MediaController::class, 'deleteMedia']);
    });

    // Phase 3: Content Moderation
    Route::prefix('moderation')->group(function () {
        Route::post('/report', [App\Http\Controllers\Api\ModerationController::class, 'reportContent']);
        Route::get('/reports', [App\Http\Controllers\Api\ModerationController::class, 'getReports']); // Admin only
        Route::put('/reports/{report}', [App\Http\Controllers\Api\ModerationController::class, 'updateReportStatus']); // Admin only
        Route::get('/stats', [App\Http\Controllers\Api\ModerationController::class, 'getContentStats']); // Admin only
    });

    // Phase 3: Push Notifications
    Route::prefix('push')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\PushNotificationController::class, 'registerDevice']);
        Route::delete('/unregister/{token}', [App\Http\Controllers\Api\PushNotificationController::class, 'unregisterDevice']);
        Route::post('/test', [App\Http\Controllers\Api\PushNotificationController::class, 'testNotification']);
        Route::get('/devices', [App\Http\Controllers\Api\PushNotificationController::class, 'getDevices']);
    });

    // Mention System
    Route::prefix('mentions')->group(function () {
        Route::get('/search-users', [App\Http\Controllers\Api\MentionController::class, 'searchUsers']);
        Route::get('/my-mentions', [App\Http\Controllers\Api\MentionController::class, 'getUserMentions']);
        Route::get('/{type}/{id}', [App\Http\Controllers\Api\MentionController::class, 'getMentions'])
            ->where('type', 'post|comment');
    });

    // Real-time Features
    Route::prefix('realtime')->group(function () {
        Route::post('/status', [App\Http\Controllers\Api\OnlineStatusController::class, 'updateStatus']);
        Route::get('/online-users', [App\Http\Controllers\Api\OnlineStatusController::class, 'getOnlineUsers']);
        Route::get('/timeline', [App\Http\Controllers\Api\TimelineController::class, 'liveTimeline']);
        Route::get('/posts/{post}', [App\Http\Controllers\Api\TimelineController::class, 'getPostUpdates']);
    });
});
