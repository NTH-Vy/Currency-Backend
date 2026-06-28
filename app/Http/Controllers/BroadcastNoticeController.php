<?php

namespace App\Http\Controllers;

use App\Models\BroadcastNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BroadcastNoticeController extends Controller
{
    /**
     * Get all active broadcast notices for users
     */
    public function index()
    {
        $notices = BroadcastNotice::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['notice_id', 'title', 'content', 'created_at']);

        return response()->json([
            'success' => true,
            'notices' => $notices,
        ]);
    }

    /**
     * Create a new broadcast notice (admin only)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $notice = BroadcastNotice::create([
            'admin_id' => Auth::id(),
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notice published successfully',
            'notice' => $notice,
        ], 201);
    }

    /**
     * Delete a broadcast notice (admin only)
     */
    public function destroy($noticeId)
    {
        $notice = BroadcastNotice::findOrFail($noticeId);
        $notice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notice deleted successfully',
        ]);
    }

    /**
     * Toggle notice active status (admin only)
     */
    public function toggleStatus($noticeId)
    {
        $notice = BroadcastNotice::findOrFail($noticeId);
        $notice->is_active = !$notice->is_active;
        $notice->save();

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notice status updated',
            'is_active' => $notice->is_active,
        ]);
    }
}
