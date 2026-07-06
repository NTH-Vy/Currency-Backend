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
            $notifications = Notification::with('actor')
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            $grouped = $notifications->groupBy(function ($notification) {
                if (in_array($notification->type, ['like', 'reply', 'mention']) && $notification->post_id) {
                    return sprintf('%s|%s', $notification->type, $notification->post_id);
                }

                return sprintf('single|%s', $notification->notification_id);
            });

            $notifications = $grouped->map(function ($group) {
                $latest = $group->sortByDesc('created_at')->first();
                $actorUsername = $latest->actor_username;
                $actorAvatar = null;
                $actorFacebookId = null;
                $actorGoogleId = null;

                if ($latest->actor) {
                    $actorUsername = $latest->actor->username ?? $actorUsername;
                    $actorAvatar = $latest->actor->avatar_url;
                    $actorFacebookId = $latest->actor->facebook_id;
                    $actorGoogleId = $latest->actor->google_id;
                }

                $groupedCount = $group->count();
                $groupedActorUsernames = $group->pluck('actor_username')->filter()->unique()->values()->all();
                $groupedActorIds = $group->pluck('actor_id')->filter()->unique()->values()->all();
                $isRead = $group->every(fn ($item) => $item->is_read == 1) ? 1 : 0;

                return [
                    'notification_id' => $latest->notification_id,
                    'type' => $latest->type,
                    'actor_id' => $latest->actor_id,
                    'actor_username' => $actorUsername ?? 'Unknown',
                    'actor_avatar' => $actorAvatar,
                    'actor_facebook_id' => $actorFacebookId,
                    'actor_google_id' => $actorGoogleId,
                    'post_id' => $latest->post_id,
                    'comment_id' => $latest->comment_id,
                    'comment_content' => $latest->comment_content,
                    'is_read' => $isRead,
                    'created_at' => $latest->created_at,
                    'grouped_count' => $groupedCount,
                    'grouped_actor_usernames' => $groupedActorUsernames,
                    'grouped_actor_ids' => $groupedActorIds,
                    'grouped' => $groupedCount > 1,
                ];
            })->slice(0, 20)->values();

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