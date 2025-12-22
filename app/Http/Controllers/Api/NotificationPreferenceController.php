<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request)
    {
        $preferences = $request->user()->notification_preferences ?? [
            'email' => [
                'likes' => true,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'reposts' => true,
                'messages' => true,
            ],
            'push' => [
                'likes' => true,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'reposts' => true,
                'messages' => true,
            ],
            'in_app' => [
                'likes' => true,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'reposts' => true,
                'messages' => true,
            ],
        ];

        return response()->json(['preferences' => $preferences]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.email' => 'required|array',
            'preferences.push' => 'required|array',
            'preferences.in_app' => 'required|array',
        ]);

        $user = $request->user();
        $user->notification_preferences = $request->preferences;
        $user->save();

        return response()->json([
            'message' => 'تنظیمات اطلاعرسانی بروزرسانی شد',
            'preferences' => $user->notification_preferences,
        ]);
    }

    public function updateType(Request $request, $type)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        if (! in_array($type, ['email', 'push', 'in_app'])) {
            return response()->json(['message' => 'نوع اطلاعرسانی نامعتبر'], 400);
        }

        $user = $request->user();
        $preferences = $user->notification_preferences ?? [];

        // Enable/disable all notifications for this type
        $preferences[$type] = array_fill_keys([
            'likes', 'comments', 'follows', 'mentions', 'reposts', 'messages',
        ], $request->enabled);

        $user->notification_preferences = $preferences;
        $user->save();

        return response()->json([
            'message' => "اطلاعرسانی {$type} " . ($request->enabled ? 'فعال' : 'غیرفعال') . ' شد',
            'preferences' => $user->notification_preferences,
        ]);
    }

    public function updateSpecific(Request $request, $type, $category)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $validTypes = ['email', 'push', 'in_app'];
        $validCategories = ['likes', 'comments', 'follows', 'mentions', 'reposts', 'messages'];

        if (! in_array($type, $validTypes) || ! in_array($category, $validCategories)) {
            return response()->json(['message' => 'نوع یا دسته اطلاعرسانی نامعتبر'], 400);
        }

        $user = $request->user();
        $preferences = $user->notification_preferences ?? [];

        if (! isset($preferences[$type])) {
            $preferences[$type] = [];
        }

        $preferences[$type][$category] = $request->enabled;
        $user->notification_preferences = $preferences;
        $user->save();

        return response()->json([
            'message' => "تنظیمات {$category} برای {$type} بروزرسانی شد",
            'preferences' => $user->notification_preferences,
        ]);
    }
}
