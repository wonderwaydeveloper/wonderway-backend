<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    protected $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function enable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password'],
            ]);
        }

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is already enabled',
            ], 400);
        }

        $secret = $this->twoFactorService->generateSecret();
        $qrCodeUrl = $this->twoFactorService->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $user->update([
            'two_factor_secret' => encrypt($secret),
        ]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'message' => 'Scan QR code with Google Authenticator and verify',
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json([
                'message' => '2FA not initialized',
            ], 400);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->twoFactorService->verifyCode($secret, $request->code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code'],
            ]);
        }

        $backupCodes = $this->twoFactorService->generateBackupCodes();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_backup_codes' => encrypt(json_encode($backupCodes)),
        ]);

        return response()->json([
            'message' => '2FA enabled successfully',
            'backup_codes' => $backupCodes,
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password'],
            ]);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
        ]);

        return response()->json([
            'message' => '2FA disabled successfully',
        ]);
    }

    public function backupCodes(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is not enabled',
            ], 400);
        }

        $backupCodes = json_decode(decrypt($user->two_factor_backup_codes), true);

        return response()->json([
            'backup_codes' => $backupCodes,
        ]);
    }
}
