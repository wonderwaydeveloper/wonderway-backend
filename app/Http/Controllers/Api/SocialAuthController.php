<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            return $this->createOrUpdateUser($googleUser, 'google');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Google authentication failed'], 401);
        }
    }

    public function redirectToGithub()
    {
        return response()->json([
            'url' => Socialite::driver('github')->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function handleGithubCallback(Request $request)
    {
        try {
            $githubUser = Socialite::driver('github')->stateless()->user();

            return $this->createOrUpdateUser($githubUser, 'github');
        } catch (\Exception $e) {
            return response()->json(['error' => 'GitHub authentication failed'], 401);
        }
    }

    public function redirectToFacebook()
    {
        return response()->json([
            'url' => Socialite::driver('facebook')->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function handleFacebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            return $this->createOrUpdateUser($facebookUser, 'facebook');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Facebook authentication failed'], 401);
        }
    }

    private function createOrUpdateUser($socialUser, $provider)
    {
        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'username' => $this->generateUsername($socialUser->getName()),
                'password' => Hash::make(uniqid()),
                'email_verified_at' => now(),
                'avatar' => $socialUser->getAvatar(),
            ]);
            $user->assignRole('user');
        }

        $user->update([
            "{$provider}_id" => $socialUser->getId(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    private function generateUsername($name)
    {
        $username = str_replace(' ', '', strtolower($name));
        $count = User::where('username', 'like', $username . '%')->count();

        return $count > 0 ? $username . $count : $username;
    }
}
