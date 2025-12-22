<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    protected $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_type' => 'required|in:android,ios,web',
            'device_name' => 'nullable|string|max:100',
        ]);

        try {
            $device = DeviceToken::updateOrCreate([
                'user_id' => auth()->id(),
                'token' => $request->token,
            ], [
                'device_type' => $request->device_type,
                'device_name' => $request->device_name,
                'active' => true,
                'last_used_at' => now(),
            ]);

            return response()->json([
                'message' => 'دستگاه با موفقیت ثبت شد',
                'device_id' => $device->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ثبت دستگاه',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function unregisterDevice(Request $request, $token)
    {
        try {
            DeviceToken::where('user_id', auth()->id())
                ->where('token', $token)
                ->update(['active' => false]);

            return response()->json(['message' => 'دستگاه غیرفعال شد']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در غیرفعال کردن دستگاه',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function testNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:200',
        ]);

        try {
            $devices = auth()->user()->devices()->where('active', true)->get();

            if ($devices->isEmpty()) {
                return response()->json(['message' => 'هیچ دستگاه فعالی یافت نشد'], 404);
            }

            $successCount = 0;
            foreach ($devices as $device) {
                $result = $this->pushService->sendToDevice(
                    $device->token,
                    $request->title,
                    $request->body
                );
                if ($result) {
                    $successCount++;
                }
            }

            return response()->json([
                'message' => 'اعلان تست ارسال شد',
                'sent_to' => $successCount,
                'total_devices' => $devices->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ارسال اعلان تست',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDevices(Request $request)
    {
        $devices = auth()->user()->devices()
            ->select('id', 'device_type', 'device_name', 'active', 'last_used_at', 'created_at')
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json(['devices' => $devices]);
    }
}
