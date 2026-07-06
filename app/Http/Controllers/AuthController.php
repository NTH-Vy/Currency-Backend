<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users',
            'email'    => 'required|email|max:100|unique:users',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'username'      => $request->username,
            'email'         => $request->email,
            'password_hash' => Hash::make($request->password),
            'role'          => 'user',
            'is_active'     => 1,
            'avatar_url'    => null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Success',
            'access_token' => $token,
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'facebook_id' => $user->facebook_id,
                'google_id' => $user->google_id,
            ]
        ], 201);
    }

    public function login(Request $request) {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Thông tin đăng nhập không chính xác'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'facebook_id' => $user->facebook_id,
                'google_id' => $user->google_id,
            ]
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function getProfile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'facebook_id' => $user->facebook_id,
                'google_id' => $user->google_id,
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|required|string|max:50|unique:users,username,' . $user->user_id . ',user_id',
            'email' => 'sometimes|required|email|max:100|unique:users,email,' . $user->user_id . ',user_id',
            'preferred_currency' => 'sometimes|required|string|max:3|exists:currencies,currency_code',
            'avatar_url' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('username')) {
            $user->username = $request->username;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('preferred_currency')) {
            $user->preferred_currency = $request->preferred_currency;
        }
        if ($request->has('avatar_url')) {
            $user->avatar_url = $request->avatar_url;
        }

        $user->save();

        return response()->json([
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'facebook_id' => $user->facebook_id,
                'google_id' => $user->google_id,
            ]
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:6|different:current_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password_hash = Hash::make($request->new_password);
        $user->save();

        // Revoke all tokens for security
        $user->tokens()->delete();

        return response()->json(['message' => 'Password changed successfully']);
    }

    // --- GOOGLE OAUTH ---

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                $rawName = $googleUser->getName();
                $username = $rawName;
                $counter = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $rawName . ' ' . $counter;
                    $counter++;
                }

                $user = User::create([
                    'username'      => $username,
                    'email'         => $googleUser->email,
                    'google_id'     => $googleUser->id,
                    'avatar_url'    => $googleUser->avatar,
                    'password_hash' => Hash::make(Str::random(16)),
                    'role'          => 'user',
                    'is_active'     => 1,
                    'preferred_currency' => 'VND'
                ]);
            } else {
                if (empty($user->google_id)) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'avatar_url' => $googleUser->avatar ?? $user->avatar_url,
                    ]);
                }
                if (empty($user->avatar_url) && $googleUser->avatar) {
                    $user->update(['avatar_url' => $googleUser->avatar]);
                }
            }

            return $this->sendSuccessResponse($user);
        } catch (\Exception $e) {
            return redirect("https://nhvy.vercel.app/");
        }
    }

    // --- FACEBOOK OAUTH ---

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            
            $user = User::where('email', $facebookUser->email)
                        ->orWhere('facebook_id', $facebookUser->id)
                        ->first();

            if (!$user) {
                $rawName = $facebookUser->getName() ?: 'Facebook User';
                $username = $rawName;
                $counter = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $rawName . ' ' . $counter;
                    $counter++;
                }

                $user = User::create([
                    'username'      => $username,
                    'email'         => $facebookUser->email,
                    'facebook_id'   => $facebookUser->id,
                    'avatar_url'    => $facebookUser->avatar,
                    'password_hash' => Hash::make(Str::random(16)),
                    'role'          => 'user',
                    'is_active'     => 1,
                    'preferred_currency' => 'VND'
                ]);
            } else {
                if (empty($user->facebook_id)) {
                    $user->update([
                        'facebook_id' => $facebookUser->id,
                        'avatar_url' => $facebookUser->avatar ?? $user->avatar_url,
                    ]);
                }
                if (empty($user->avatar_url) && $facebookUser->avatar) {
                    $user->update(['avatar_url' => $facebookUser->avatar]);
                }
            }

            return $this->sendSuccessResponse($user);
        } catch (\Exception $e) {
            return redirect("http://localhost:3000/login?error=facebook_failed");
        }
    }

    /**
     * Hàm phụ trợ để trả về kết quả cho Next.js
     */
    private function sendSuccessResponse($user)
    {
        $token = $user->createToken('auth_token')->plainTextToken;
        $userData = urlencode(json_encode([
            'user_id' => $user->user_id,
            'username' => $user->username,
            'email'    => $user->email,
            'role'     => $user->role,
            'avatar_url' => $user->avatar_url,
            'facebook_id' => $user->facebook_id,
            'google_id' => $user->google_id,
        ]));

        return redirect("http://localhost:3000/login-success?token={$token}&user={$userData}");
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}