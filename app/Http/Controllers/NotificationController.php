<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index()
    {
        try {
            $notifications = Notification::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($notification) {
                    return [
                        'notification_id' => $notification->notification_id,
                        'type' => $notification->type,
                        'actor_id' => $notification->actor_id,
                        'actor_username' => $notification->actor_username ?? 'Unknown',
                        'post_id' => $notification->post_id,
                        'comment_id' => $notification->comment_id,
                        'comment_content' => $notification->comment_content,
                        'is_read' => $notification->is_read,
                        'created_at' => $notification->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead($notificationId)
    {
        $notification = Notification::where('notification_id', $notificationId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $notification->is_read = 1;
        $notification->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read for the authenticated user
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Get unread notification count
     */
    public function unreadCount()
    {
        try {
            $count = Notification::where('user_id', Auth::id())
                ->where('is_read', 0)
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching unread count: ' . $e->getMessage()
            ], 500);
        }
    }
}
