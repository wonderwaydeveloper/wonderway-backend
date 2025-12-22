<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PhoneAuthController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function sendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9+]{10,15}$/',
        ]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneVerificationCode::create([
            'phone' => $request->phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->smsService->sendVerificationCode($request->phone, $code);

        return response()->json([
            'message' => 'Verification code sent successfully',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $verification = PhoneVerificationCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('verified', false)
            ->latest()
            ->first();

        if (! $verification || $verification->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code'],
            ]);
        }

        $verification->update(['verified' => true]);

        return response()->json([
            'message' => 'Phone verified successfully',
            'verified' => true,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|unique:users',
            'code' => 'required|string|size:6',
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'date_of_birth' => 'required|date',
        ]);

        $verification = PhoneVerificationCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('verified', true)
            ->latest()
            ->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number not verified'],
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'phone' => $request->phone,
            'phone_verified_at' => now(),
            'password' => Hash::make($request->password),
            'date_of_birth' => $request->date_of_birth,
        ]);

        $user->assignRole('user');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
