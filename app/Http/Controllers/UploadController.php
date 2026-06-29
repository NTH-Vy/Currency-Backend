<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    // 1. Xử lý upload ảnh cho Tin tức
    public function uploadNews(Request $request)
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('news', 'public');
            
            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }
        return response()->json(['error' => 'Không tìm thấy file ảnh'], 400);
    }

    // 2. Xử lý upload ảnh đại diện User (cũ)
    public function uploadUser(Request $request)
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('users', 'public');
            
            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }
        return response()->json(['error' => 'Không tìm thấy file ảnh'], 400);
    }

    // 3. Upload avatar cho user đang đăng nhập
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($request->hasFile('avatar')) {
            // Xóa avatar cũ nếu có và là file local
            if ($user->avatar_url && !filter_var($user->avatar_url, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($user->avatar_url);
            }

            // Lưu avatar mới
            $path = $request->file('avatar')->store('avatars', 'public');
            
            // Cập nhật user
            $user->avatar_url = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Avatar updated successfully',
                'avatar_url' => asset('storage/' . $path),
                'user' => [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_url' => asset('storage/' . $path),
                    'facebook_id' => $user->facebook_id,
                    'google_id' => $user->google_id,
                ]
            ]);
        }

        return response()->json(['error' => 'No avatar file found'], 400);
    }

    // 4. Xóa avatar
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Không cho xóa avatar từ social
        if ($user->facebook_id || $user->google_id) {
            return response()->json(['error' => 'Cannot delete social avatar'], 400);
        }

        // Xóa file
        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->avatar_url = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully',
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => null,
                'facebook_id' => $user->facebook_id,
                'google_id' => $user->google_id,
            ]
        ]);
    }
}