<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// Public routes with security middleware
Route::middleware(['spam.detection'])->group(function () {
    Route::get('/posts', [PostController::class, 'index']);
});

// Health Check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '3.0.0',
        'environment' => app()->environment(),
    ]);
});

// Test route for security testing
Route::post('/test', function () {
    return response()->json(['message' => 'Test endpoint']);
});

Route::post('/register', [AuthController::class, 'register'])->middleware(['advanced.rate.limit:register,3,60']);
Route::post('/login', [AuthController::class, 'login'])->middleware(['advanced.rate.limit:login,5,5']);

// Add user route for testing
Route::get('/user', function () {
    return response()->json(['user' => auth()->user()]);
})->middleware('auth:sanctum');

Route::prefix('auth/phone')->middleware(['advanced.rate.limit:phone,10,5'])->group(function () {
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

Route::middleware(['auth:sanctum', 'spam.detection'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('posts', PostController::class)->middleware(['advanced.rate.limit:posts,10,1']);
    Route::put('/posts/{post}', [PostController::class, 'update'])->middleware(['advanced.rate.limit:posts,5,1']);
    Route::get('/posts/{post}/edit-history', [PostController::class, 'editHistory']);
    Route::post('/posts/{post}/like', [PostController::class, 'like'])->middleware(['advanced.rate.limit:likes,60,1']);
    Route::post('/posts/{post}/quote', [PostController::class, 'quote'])->middleware(['advanced.rate.limit:posts,10,1']);
    Route::get('/timeline', [PostController::class, 'timeline']);
    Route::get('/drafts', [PostController::class, 'drafts']);
    Route::post('/posts/{post}/publish', [PostController::class, 'publish']);

    Route::post('/threads', [App\Http\Controllers\Api\ThreadController::class, 'create']);
    Route::get('/threads/{post}', [App\Http\Controllers\Api\ThreadController::class, 'show']);
    Route::post('/threads/{post}/add', [App\Http\Controllers\Api\ThreadController::class, 'addToThread']);
    Route::get('/threads/{post}/stats', [App\Http\Controllers\Api\ThreadController::class, 'stats']);

    Route::post('/scheduled-posts', [App\Http\Controllers\Api\ScheduledPostController::class, 'store']);
    Route::get('/scheduled-posts', [App\Http\Controllers\Api\ScheduledPostController::class, 'index']);
    Route::delete('/scheduled-posts/{scheduledPost}', [App\Http\Controllers\Api\ScheduledPostController::class, 'destroy']);

    Route::get('/gifs/search', [App\Http\Controllers\Api\GifController::class, 'search']);
    Route::get('/gifs/trending', [App\Http\Controllers\Api\GifController::class, 'trending']);

    Route::get('/bookmarks', [App\Http\Controllers\Api\BookmarkController::class, 'index']);
    Route::post('/posts/{post}/bookmark', [App\Http\Controllers\Api\BookmarkController::class, 'toggle']);

    Route::post('/posts/{post}/repost', [App\Http\Controllers\Api\RepostController::class, 'repost']);
    Route::get('/posts/{post}/quotes', [PostController::class, 'quotes']);
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
    Route::get('/search/hashtags', [App\Http\Controllers\Api\SearchController::class, 'hashtags']);
    Route::get('/search/all', [App\Http\Controllers\Api\SearchController::class, 'all']);
    Route::get('/search/advanced', [App\Http\Controllers\Api\SearchController::class, 'advanced']);
    Route::get('/search/suggestions', [App\Http\Controllers\Api\SearchController::class, 'suggestions']);
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
    Route::get('/hashtags/suggestions', [App\Http\Controllers\Api\HashtagController::class, 'suggestions']);
    Route::get('/hashtags/{hashtag:slug}', [App\Http\Controllers\Api\HashtagController::class, 'show']);

    // Advanced Trending Routes
    Route::prefix('trending')->group(function () {
        Route::get('/hashtags', [App\Http\Controllers\Api\TrendingController::class, 'hashtags']);
        Route::get('/posts', [App\Http\Controllers\Api\TrendingController::class, 'posts']);
        Route::get('/users', [App\Http\Controllers\Api\TrendingController::class, 'users']);
        Route::get('/personalized', [App\Http\Controllers\Api\TrendingController::class, 'personalized']);
        Route::get('/velocity/{type}/{id}', [App\Http\Controllers\Api\TrendingController::class, 'velocity']);
        Route::get('/all', [App\Http\Controllers\Api\TrendingController::class, 'all']);
        Route::get('/stats', [App\Http\Controllers\Api\TrendingController::class, 'stats']);
        Route::post('/refresh', [App\Http\Controllers\Api\TrendingController::class, 'refresh']);
    });

    // Spaces (Audio Rooms) Routes
    Route::prefix('spaces')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SpaceController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\SpaceController::class, 'store']);
        Route::get('/{space}', [App\Http\Controllers\Api\SpaceController::class, 'show']);
        Route::post('/{space}/join', [App\Http\Controllers\Api\SpaceController::class, 'join']);
        Route::post('/{space}/leave', [App\Http\Controllers\Api\SpaceController::class, 'leave']);
        Route::put('/{space}/participants/{participant}/role', [App\Http\Controllers\Api\SpaceController::class, 'updateRole']);
        Route::post('/{space}/end', [App\Http\Controllers\Api\SpaceController::class, 'end']);
    });

    // Lists Routes
    Route::prefix('lists')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ListController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\ListController::class, 'store']);
        Route::get('/discover', [App\Http\Controllers\Api\ListController::class, 'discover']);
        Route::get('/{list}', [App\Http\Controllers\Api\ListController::class, 'show']);
        Route::put('/{list}', [App\Http\Controllers\Api\ListController::class, 'update']);
        Route::delete('/{list}', [App\Http\Controllers\Api\ListController::class, 'destroy']);
        Route::post('/{list}/members', [App\Http\Controllers\Api\ListController::class, 'addMember']);
        Route::delete('/{list}/members/{user}', [App\Http\Controllers\Api\ListController::class, 'removeMember']);
        Route::post('/{list}/subscribe', [App\Http\Controllers\Api\ListController::class, 'subscribe']);
        Route::post('/{list}/unsubscribe', [App\Http\Controllers\Api\ListController::class, 'unsubscribe']);
        Route::get('/{list}/posts', [App\Http\Controllers\Api\ListController::class, 'posts']);
    });

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

    // Performance Dashboard Routes
    Route::prefix('performance-dashboard')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\PerformanceDashboardController::class, 'dashboard']);
        Route::get('/metrics', [App\Http\Controllers\Api\PerformanceDashboardController::class, 'metrics']);
        Route::get('/api-stats', [App\Http\Controllers\Api\PerformanceDashboardController::class, 'apiStats']);
        Route::get('/real-time', [App\Http\Controllers\Api\PerformanceDashboardController::class, 'realTimeMetrics']);
        Route::get('/health', [App\Http\Controllers\Api\PerformanceDashboardController::class, 'systemHealth']);
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

    // Moments Routes
    Route::prefix('moments')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\MomentController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\MomentController::class, 'store']);
        Route::get('/featured', [App\Http\Controllers\Api\MomentController::class, 'featured']);
        Route::get('/my-moments', [App\Http\Controllers\Api\MomentController::class, 'myMoments']);
        Route::get('/{moment}', [App\Http\Controllers\Api\MomentController::class, 'show']);
        Route::put('/{moment}', [App\Http\Controllers\Api\MomentController::class, 'update']);
        Route::delete('/{moment}', [App\Http\Controllers\Api\MomentController::class, 'destroy']);
        Route::post('/{moment}/posts', [App\Http\Controllers\Api\MomentController::class, 'addPost']);
        Route::delete('/{moment}/posts/{post}', [App\Http\Controllers\Api\MomentController::class, 'removePost']);
    });

    // A/B Testing Routes (Admin only in production)
    Route::prefix('ab-tests')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ABTestController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\ABTestController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\ABTestController::class, 'show']);
        Route::post('/{id}/start', [App\Http\Controllers\Api\ABTestController::class, 'start']);
        Route::post('/{id}/stop', [App\Http\Controllers\Api\ABTestController::class, 'stop']);
        Route::post('/assign', [App\Http\Controllers\Api\ABTestController::class, 'assign']);
        Route::post('/track', [App\Http\Controllers\Api\ABTestController::class, 'track']);
    });

    // Live Streaming Routes
    Route::prefix('streams')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\LiveStreamController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\LiveStreamController::class, 'store']);
        Route::get('/{stream}', [App\Http\Controllers\Api\LiveStreamController::class, 'show']);
        Route::post('/{stream}/start', [App\Http\Controllers\Api\LiveStreamController::class, 'start']);
        Route::post('/{stream}/end', [App\Http\Controllers\Api\LiveStreamController::class, 'end']);
        Route::post('/{stream}/join', [App\Http\Controllers\Api\LiveStreamController::class, 'join']);
        Route::post('/{stream}/leave', [App\Http\Controllers\Api\LiveStreamController::class, 'leave']);
    });

    // Conversion Tracking Routes
    Route::prefix('conversions')->group(function () {
        Route::post('/track', [App\Http\Controllers\Api\ConversionController::class, 'track']);
        Route::get('/funnel', [App\Http\Controllers\Api\ConversionController::class, 'funnel']);
        Route::get('/by-source', [App\Http\Controllers\Api\ConversionController::class, 'bySource']);
        Route::get('/user-journey', [App\Http\Controllers\Api\ConversionController::class, 'userJourney']);
        Route::get('/cohort-analysis', [App\Http\Controllers\Api\ConversionController::class, 'cohortAnalysis']);
    });

    // Auto-scaling Routes (Admin only in production)
    Route::prefix('auto-scaling')->group(function () {
        Route::get('/status', [App\Http\Controllers\Api\AutoScalingController::class, 'status']);
        Route::get('/metrics', [App\Http\Controllers\Api\AutoScalingController::class, 'metrics']);
        Route::get('/history', [App\Http\Controllers\Api\AutoScalingController::class, 'history']);
        Route::get('/predict', [App\Http\Controllers\Api\AutoScalingController::class, 'predict']);
        Route::post('/force-scale', [App\Http\Controllers\Api\AutoScalingController::class, 'forceScale']);
    });
    // Monetization Routes
    Route::prefix('monetization')->group(function () {
        // Advertisement Routes
        Route::prefix('ads')->group(function () {
            Route::post('/', [App\Monetization\Controllers\AdvertisementController::class, 'create']);
            Route::get('/targeted', [App\Monetization\Controllers\AdvertisementController::class, 'getTargetedAds']);
            Route::post('/{adId}/click', [App\Monetization\Controllers\AdvertisementController::class, 'recordClick']);
            Route::get('/analytics', [App\Monetization\Controllers\AdvertisementController::class, 'getAnalytics']);
            Route::post('/{adId}/pause', [App\Monetization\Controllers\AdvertisementController::class, 'pause']);
            Route::post('/{adId}/resume', [App\Monetization\Controllers\AdvertisementController::class, 'resume']);
        });

        // Creator Fund Routes
        Route::prefix('creator-fund')->group(function () {
            Route::get('/analytics', [App\Monetization\Controllers\CreatorFundController::class, 'getAnalytics']);
            Route::post('/calculate-earnings', [App\Monetization\Controllers\CreatorFundController::class, 'calculateEarnings']);
            Route::get('/earnings-history', [App\Monetization\Controllers\CreatorFundController::class, 'getEarningsHistory']);
            Route::post('/request-payout', [App\Monetization\Controllers\CreatorFundController::class, 'requestPayout']);
        });

        // Premium Subscription Routes
        Route::prefix('premium')->group(function () {
            Route::get('/plans', [App\Monetization\Controllers\PremiumController::class, 'getPlans']);
            Route::post('/subscribe', [App\Monetization\Controllers\PremiumController::class, 'subscribe']);
            Route::post('/cancel', [App\Monetization\Controllers\PremiumController::class, 'cancel']);
            Route::get('/status', [App\Monetization\Controllers\PremiumController::class, 'getStatus']);
        });
    });

    // Include streaming routes with security middleware
    require __DIR__.'/streaming.php';
});
