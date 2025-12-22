<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function forgot(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $token = Str::random(60);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Send password reset email
        $this->emailService->sendPasswordResetEmail($user, $token);

        return response()->json([
            'message' => 'Password reset link sent to your email',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $tokenData = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $tokenData || ! Hash::check($request->token, $tokenData->token)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid token'],
            ]);
        }

        if (now()->diffInMinutes($tokenData->created_at) > 60) {
            throw ValidationException::withMessages([
                'token' => ['Token expired'],
            ]);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'valid' => false,
                'message' => 'User not found',
            ], 404);
        }

        $tokenData = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $tokenData) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid token',
            ], 400);
        }

        if (! Hash::check($request->token, $tokenData->token)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid token',
            ], 400);
        }

        if (now()->diffInMinutes($tokenData->created_at) > 60) {
            return response()->json([
                'valid' => false,
                'message' => 'Token expired',
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token is valid',
        ]);
    }
}
