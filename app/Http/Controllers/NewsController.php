<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Comment;
use App\Models\Favorite;
use App\Models\CommentLike;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    /**
     * Lấy danh sách tin tức với hỗ trợ search, category và pagination
     */
    public function index(Request $request) {
        $query = News::with('author:user_id,username');

        // 1. Lọc theo Category (Nếu giá trị truyền lên khác 'All News')
        if ($request->has('category') && $request->category != 'All News') {
            $query->where('category', $request->category);
        }

        // 2. Tìm kiếm theo tiêu đề hoặc nội dung
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('content', 'like', "%$search%");
            });
        }

        // 3. Phân trang (Pagination) - Lấy 9 bài mỗi trang
        // Sắp xếp theo lượt xem giảm dần (tin hot lên đầu)
        $paginated = $query->orderBy('views', 'desc')->paginate(9);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total()
            ]
        ]);
    }

    /**
     * Hiển thị chi tiết tin tức
     */
    public function show(Request $request, $id) {
        $news = News::with([
            'author:user_id,username',
            'comments.user:user_id,username',
            'comments.parentComment.user:user_id,username'
        ])->find($id);

        if (!$news) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Tăng lượt xem
        $news->increment('views');

        $isFavorited = false;
        $userId = null;
        if ($user = auth('sanctum')->user()) {
            $userId = $user->user_id;
            $isFavorited = Favorite::where('user_id', $userId)
                                    ->where('news_id', $id)->exists();
        }

        // Add like status and like count to comments
        if ($news->comments) {
            $commentIds = $news->comments->pluck('comment_id')->toArray();
            $likesCount = CommentLike::whereIn('comment_id', $commentIds)
                ->selectRaw('comment_id, COUNT(*) as count')
                ->groupBy('comment_id')
                ->pluck('count', 'comment_id')
                ->toArray();

            $likedCommentIds = [];
            if ($userId) {
                $likedCommentIds = CommentLike::where('user_id', $userId)
                    ->whereIn('comment_id', $commentIds)
                    ->pluck('comment_id')
                    ->toArray();
            }

            $news->comments->transform(function ($comment) use ($likesCount, $likedCommentIds) {
                $comment->likes = $likesCount[$comment->comment_id] ?? 0;
                $comment->is_liked = in_array($comment->comment_id, $likedCommentIds);
                return $comment;
            });
        }

        return response()->json([
            'news' => $news,
            'is_favorited' => $isFavorited
        ]);
    }

    /**
     * Gửi bình luận (có kiểm tra user bị ban)
     */
    public function postComment(Request $request, $id) {
        $user = $request->user();
        
        // Kiểm tra user bị cấm comment
        if ($user->isBannedFromCommenting()) {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị tạm khóa tính năng bình luận',
                'banned_until' => $user->banned_until,
                'ban_reason' => $user->ban_reason,
                'ban_remaining' => $user->getBanRemainingAttribute()
            ], 403);
        }

        $request->validate([
            'content' => 'required|string',
            'rating' => 'integer|between:1,5',
            'parent_comment_id' => 'nullable|integer|exists:comments,comment_id'
        ]);

        $comment = Comment::create([
            'user_id' => $user->user_id,
            'news_id' => $id,
            'content' => $request->content,
            'rating' => $request->rating ?? 5,
            'parent_comment_id' => $request->parent_comment_id
        ]);

        return response()->json([
            'message' => 'Success',
            'comment' => $comment->load('user:user_id,username')
        ]);
    }

    /**
     * Xóa bình luận
     */
    public function deleteComment(Request $request, $id, $commentId) {
        $comment = Comment::where('comment_id', $commentId)->where('news_id', $id)->first();

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Chỉ cho phép xóa bình luận của chính mình hoặc admin
        if ($comment->user_id !== $request->user()->user_id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * Sửa bình luận
     */
    public function updateComment(Request $request, $id, $commentId) {
        $request->validate([
            'content' => 'required|string'
        ]);

        $comment = Comment::where('comment_id', $commentId)->where('news_id', $id)->first();

        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Chỉ cho phép sửa bình luận của chính mình hoặc admin
        if ($comment->user_id !== $request->user()->user_id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->update(['content' => $request->content]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment->load('user:user_id,username')
        ]);
    }

    /**
     * Yêu thích / Bỏ yêu thích
     */
    public function toggleFavorite(Request $request, $id) {
        $userId = $request->user()->user_id;
        $favorite = Favorite::where('user_id', $userId)->where('news_id', $id)->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['is_favorited' => false]);
        } else {
            Favorite::create(['user_id' => $userId, 'news_id' => $id]);
            return response()->json(['is_favorited' => true]);
        }
    }

    /**
     * Lấy tin đang trending (tin có xu hướng gia tăng)
     */
    public function trending(Request $request) {
        $query = News::with('author:user_id,username');

        // Sắp xếp theo lượt xem giảm dần, ưu tiên tin mới hơn
        // Công thức: views / (số ngày kể từ khi đăng + 1) để tính độ hot
        $query->orderByRaw('views / (DATEDIFF(NOW(), published_at) + 1) DESC');

        // Lọc theo Category nếu có
        if ($request->has('category') && $request->category != 'All News') {
            $query->where('category', $request->category);
        }

        // Lấy top 8 tin trending
        return $query->limit(8)->get();
    }

    /**
     * Lấy tin liên quan theo category
     */
    public function related(Request $request, $id) {
        $news = News::find($id);

        if (!$news) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $query = News::with('author:user_id,username')
            ->where('category', $news->category)
            ->where('news_id', '!=', $id)
            ->orderBy('views', 'desc')
            ->limit(5);

        return $query->get();
    }

    /**
     * Lấy bình luận của người dùng hiện tại kèm theo replies
     */
    public function getUserComments(Request $request) {
        $userId = $request->user()->user_id;

        // Lấy tất cả bình luận của user (bao gồm cả replies)
        $userComments = Comment::with([
            'user:user_id,username',
            'news:news_id,title',
            'replies.user:user_id,username',
            'parentComment.user:user_id,username'
        ])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Lấy các replies đến bình luận của user
        $userCommentIds = $userComments->pluck('comment_id');
        $repliesToUser = Comment::with([
            'user:user_id,username',
            'parentComment',
            'news:news_id,title'
        ])
            ->whereIn('parent_comment_id', $userCommentIds)
            ->where('user_id', '!=', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user_comments' => $userComments,
            'replies_to_user' => $repliesToUser
        ]);
    }

    /**
     * Kiểm tra trạng thái ban của user hiện tại
     */
    public function checkBanStatus(Request $request) {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['banned' => false]);
        }

        return response()->json([
            'banned' => $user->isBannedFromCommenting(),
            'banned_until' => $user->banned_until,
            'ban_reason' => $user->ban_reason,
            'ban_remaining' => $user->ban_remaining
        ]);
    }

    /**
     * Lấy danh sách các báo cáo của user hiện tại
     */
    public function getUserReports(Request $request) {
        $user = $request->user();
        
        $reports = \App\Models\Report::with(['comment.news', 'comment.user'])
            ->where('reporter_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reports);
    }

    /**
     * Like / Unlike comment
     */
    public function likeComment(Request $request, $id, $commentId) {
        $user = $request->user();
        $userId = $user->user_id;

        // Kiểm tra comment tồn tại
        $comment = Comment::where('comment_id', $commentId)->where('news_id', $id)->first();
        if (!$comment) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        $like = CommentLike::where('user_id', $userId)->where('comment_id', $commentId)->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            CommentLike::create(['user_id' => $userId, 'comment_id' => $commentId]);
            $liked = true;
        }

        // Lấy số likes mới
        $likesCount = CommentLike::where('comment_id', $commentId)->count();

        return response()->json([
            'liked' => $liked,
            'likes' => $likesCount
        ]);
    }

    /**
     * Lấy bình luận phân trang cho một bài viết
     */
    public function getComments(Request $request, $id) {
        $page = $request->get('page', 1);
        $perPage = 5;

        // Lấy user ID hiện tại để check like status
        $userId = null;
        if ($user = auth('sanctum')->user()) {
            $userId = $user->user_id;
        }

        // Lấy comments phân trang (chỉ lấy root comments)
        $paginatedComments = Comment::with([
            'user:user_id,username',
            'parentComment.user:user_id,username'
        ])
            ->where('news_id', $id)
            ->whereNull('parent_comment_id')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Lấy tất cả replies cho các comments trên trang hiện tại
        $commentIds = $paginatedComments->pluck('comment_id')->toArray();
        $replies = Comment::with(['user:user_id,username'])
            ->whereIn('parent_comment_id', $commentIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // Gán replies vào các comment tương ứng
        $comments = $paginatedComments->items();
        $comments = collect($comments)->map(function ($comment) use ($replies) {
            $comment->replies = $replies->where('parent_comment_id', $comment->comment_id)->values();
            return $comment;
        });

        // Add like status và like count
        $allCommentIds = collect($comments)->pluck('comment_id')
            ->concat(collect($comments)->pluck('replies.*.comment_id')->flatten())
            ->toArray();

        $likesCount = CommentLike::whereIn('comment_id', $allCommentIds)
            ->selectRaw('comment_id, COUNT(*) as count')
            ->groupBy('comment_id')
            ->pluck('count', 'comment_id')
            ->toArray();

        $likedCommentIds = [];
        if ($userId) {
            $likedCommentIds = CommentLike::where('user_id', $userId)
                ->whereIn('comment_id', $allCommentIds)
                ->pluck('comment_id')
                ->toArray();
        }

        $comments = collect($comments)->map(function ($comment) use ($likesCount, $likedCommentIds) {
            $comment->likes = $likesCount[$comment->comment_id] ?? 0;
            $comment->is_liked = in_array($comment->comment_id, $likedCommentIds);

            $comment->replies = $comment->replies->map(function ($reply) use ($likesCount, $likedCommentIds) {
                $reply->likes = $likesCount[$reply->comment_id] ?? 0;
                $reply->is_liked = in_array($reply->comment_id, $likedCommentIds);
                return $reply;
            });

            return $comment;
        });

        return response()->json([
            'success' => true,
            'data' => $comments,
            'pagination' => [
                'current_page' => $paginatedComments->currentPage(),
                'last_page' => $paginatedComments->lastPage(),
                'per_page' => $paginatedComments->perPage(),
                'total' => $paginatedComments->total()
            ]
        ]);
    }

    /**
     * Lấy top comments (có nhiều like nhất) cho một bài viết
     */
    public function getTopComments(Request $request, $id) {
        $limit = 5;

        // Lấy user ID hiện tại để check like status
        $userId = null;
        if ($user = auth('sanctum')->user()) {
            $userId = $user->user_id;
        }

        // Lấy tất cả comments của bài viết
        $allComments = Comment::with(['user:user_id,username'])
            ->where('news_id', $id)
            ->get();

        // Đếm số likes cho mỗi comment
        $commentIds = $allComments->pluck('comment_id')->toArray();
        $likesCount = CommentLike::whereIn('comment_id', $commentIds)
            ->selectRaw('comment_id, COUNT(*) as count')
            ->groupBy('comment_id')
            ->pluck('count', 'comment_id')
            ->toArray();

        // Gán số likes vào comments
        $allComments = $allComments->map(function ($comment) use ($likesCount) {
            $comment->likes = $likesCount[$comment->comment_id] ?? 0;
            return $comment;
        });

        // Sắp xếp theo số likes giảm dần và lấy top 5
        $topComments = $allComments->sortByDesc('likes')->take($limit)->values();

        // Check like status cho user hiện tại
        $likedCommentIds = [];
        if ($userId) {
            $likedCommentIds = CommentLike::where('user_id', $userId)
                ->whereIn('comment_id', $topComments->pluck('comment_id')->toArray())
                ->pluck('comment_id')
                ->toArray();
        }

        $topComments = $topComments->map(function ($comment) use ($likedCommentIds) {
            $comment->is_liked = in_array($comment->comment_id, $likedCommentIds);
            return $comment;
        });

        return response()->json([
            'success' => true,
            'data' => $topComments
        ]);
    }
}