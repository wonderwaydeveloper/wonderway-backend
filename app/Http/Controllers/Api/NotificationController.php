<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\{SendNotificationAction, MarkAsReadAction};
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\{JsonResponse, Request};

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private MarkAsReadAction $markAsReadAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getUserNotifications(
            $request->user()->id,
            $request->get('limit', 20)
        );
        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user()->id);
        return response()->json(['count' => $count]);
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);
        $this->markAsReadAction->execute($notification);
        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user()->id);
        return response()->json(['message' => 'All marked as read']);
    }

    public function unread(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json($notifications);
    }
}
