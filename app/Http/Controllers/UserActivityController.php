<?php

namespace App\Http\Controllers;

use App\Models\ConversionHistory;
use App\Models\Comment;
use App\Models\Favorite;
use App\Models\PostLike;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    public function getAllActivities(Request $request)
    {
        // Admin can view any user's activities, regular users can only view their own
        $userId = $request->user()->user_id;
        $isAdmin = $request->user()->role === 'admin';
        
        // If admin provides a user_id parameter, use that
        if ($isAdmin && $request->has('user_id')) {
            $userId = $request->input('user_id');
        }
        
        $activities = [];
        
        // 1. Conversion history (chuyển đổi tiền tệ)
        $conversions = ConversionHistory::where('user_id', $userId)
            ->select(
                'history_id as id',
                \DB::raw("'conversion' as type"),
                'from_currency',
                'to_currency',
                'amount_input',
                'amount_output',
                'created_at'
            )
            ->get();
        
        foreach ($conversions as $conv) {
            $activities[] = [
                'id' => $conv->id,
                'type' => 'conversion',
                'action' => 'Currency Conversion',
                'title' => "{$conv->from_currency} → {$conv->to_currency}",
                'details' => "Converted " . number_format($conv->amount_input, 2) . " {$conv->from_currency} to " . number_format($conv->amount_output, 2) . " {$conv->to_currency}",
                'created_at' => $conv->created_at,
            ];
        }
        
        // 2. User's comments (bình luận của user)
        $comments = Comment::with(['news:news_id,title'])
            ->where('user_id', $userId)
            ->whereNotNull('news_id')
            ->select('comment_id as id', 'content', 'rating', 'created_at', 'news_id')
            ->get();
        
        foreach ($comments as $comment) {
            $activities[] = [
                'id' => $comment->id,
                'type' => 'comment',
                'action' => 'Comment',
                'title' => $comment->news->title ?? 'News Article',
                'details' => substr($comment->content, 0, 100) . (strlen($comment->content) > 100 ? '...' : ''),
                'rating' => $comment->rating,
                'created_at' => $comment->created_at,
            ];
        }
        
        // 3. Replies from user (các reply của user)
        $replies = Comment::with(['news:news_id,title', 'parentComment.user:user_id,username'])
            ->where('user_id', $userId)
            ->whereNotNull('parent_comment_id')
            ->select('comment_id as id', 'content', 'created_at', 'news_id', 'parent_comment_id')
            ->get();
        
        foreach ($replies as $reply) {
            $replyTo = $reply->parentComment && $reply->parentComment->user 
                ? $reply->parentComment->user->username 
                : 'Someone';
            $activities[] = [
                'id' => $reply->id,
                'type' => 'reply',
                'action' => 'Reply to Comment',
                'title' => $reply->news->title ?? 'News Article',
                'details' => "Replied to @{$replyTo}: " . substr($reply->content, 0, 80) . (strlen($reply->content) > 80 ? '...' : ''),
                'created_at' => $reply->created_at,
            ];
        }
        
        // 4. Favorited news (tin tức đã lưu)
        $favorites = Favorite::with(['news:news_id,title'])
            ->where('user_id', $userId)
            ->select('favorite_id as id', 'news_id', 'created_at')
            ->get();
        
        foreach ($favorites as $fav) {
            $activities[] = [
                'id' => $fav->id,
                'type' => 'favorite',
                'action' => 'Saved News',
                'title' => $fav->news->title ?? 'News Article',
                'details' => 'Added to favorites',
                'created_at' => $fav->created_at,
            ];
        }
        
        // 5. Likes on posts (like bài viết community)
        $likes = PostLike::with(['post:post_id,title'])
            ->where('user_id', $userId)
            ->select('like_id as id', 'post_id')
            ->get();
        
        foreach ($likes as $like) {
            $activities[] = [
                'id' => $like->id,
                'type' => 'like',
                'action' => 'Liked Post',
                'title' => $like->post->title ?? 'Community Post',
                'details' => 'Liked this post',
                'created_at' => now()->toDateTimeString(), // Use current time since table doesn't have created_at
            ];
        }
        
        // Sort by created_at (newest first)
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        $total = count($activities);
        $offset = ($page - 1) * $perPage;
        $paginatedActivities = array_slice($activities, $offset, $perPage);
        
        return response()->json([
            'success' => true,
            'activities' => $paginatedActivities,
            'total' => $total,
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'last_page' => ceil($total / $perPage),
        ]);
    }
}