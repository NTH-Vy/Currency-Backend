<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UploadController extends Controller
{
    // 1. Xử lý upload ảnh cho Tin tức
    public function uploadNews(Request $request)
    {
        if ($request->hasFile('image')) {
            // Ảnh sẽ tự động lưu vào: storage/app/public/news
            $path = $request->file('image')->store('news', 'public');
            
            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }
        return response()->json(['error' => 'Không tìm thấy file ảnh'], 400);
    }

    // 2. Xử lý upload ảnh đại diện User
    public function uploadUser(Request $request)
    {
        if ($request->hasFile('image')) {
            // Ảnh sẽ tự động lưu vào: storage/app/public/users
            $path = $request->file('image')->store('users', 'public');
            
            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }
        return response()->json(['error' => 'Không tìm thấy file ảnh'], 400);
    }
}