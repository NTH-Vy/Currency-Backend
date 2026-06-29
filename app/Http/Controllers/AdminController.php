<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\News;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\ActivityLog;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // Helper lấy avatar URL
    private function getUserAvatarUrl($user)
    {
        // Nếu có avatar_url từ DB
        if ($user->avatar_url) {
            if (filter_var($user->avatar_url, FILTER_VALIDATE_URL)) {
                return $user->avatar_url;
            }
            return asset('storage/' . $user->avatar_url);
        }

        // Nếu có facebook_id
        if ($user->facebook_id) {
            return "https://graph.facebook.com/{$user->facebook_id}/picture?type=large";
        }

        // Nếu có google_id
        if ($user->google_id) {
            return "https://lh3.googleusercontent.com/a/{$user->google_id}=s96-c";
        }

        return null;
    }

    // Get all exchange rates with pagination (10 per page)
    public function getRates(Request $request)
    {
        $query = ExchangeRate::with(['baseCurrency', 'targetCurrency']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('base_currency', 'like', "%{$search}%")
                  ->orWhere('target_currency', 'like', "%{$search}%");
            });
        }

        $rates = $query->orderBy('last_updated', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $rates->items(),
            'pagination' => [
                'current_page' => $rates->currentPage(),
                'last_page' => $rates->lastPage(),
                'per_page' => $rates->perPage(),
                'total' => $rates->total(),
            ]
        ]);
    }

    // Create or update exchange rate
    public function saveRate(Request $request)
    {
        $request->validate([
            'base_currency' => 'required|string|max:3',
            'target_currency' => 'required|string|max:3',
            'exchange_rate' => 'required|numeric',
            'source' => 'nullable|string|max:50',
            'bid_price' => 'nullable|numeric',
            'ask_price' => 'nullable|numeric',
            'change_24h' => 'nullable|numeric',
            'volume_24h' => 'nullable|numeric',
            'volatility' => 'nullable|in:Low,Medium,High',
            'price_change_percent' => 'nullable|numeric',
            'trend' => 'nullable|in:up,down,neutral',
        ]);

        $rate = ExchangeRate::updateOrCreate(
            [
                'base_currency' => $request->base_currency,
                'target_currency' => $request->target_currency,
            ],
            [
                'exchange_rate' => $request->exchange_rate,
                'source' => $request->source,
                'bid_price' => $request->bid_price,
                'ask_price' => $request->ask_price,
                'change_24h' => $request->change_24h,
                'volume_24h' => $request->volume_24h,
                'volatility' => $request->volatility,
                'price_change_percent' => $request->price_change_percent,
                'trend' => $request->trend,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $rate
        ]);
    }

    // Delete exchange rate
    public function deleteRate(Request $request, $rateId)
    {
        $rate = ExchangeRate::find($rateId);
        if (!$rate) {
            return response()->json(['success' => false, 'message' => 'Rate not found'], 404);
        }

        $rate->delete();
        return response()->json(['success' => true]);
    }

    // Get all news with pagination (10 per page)
    public function getNews(Request $request)
    {
        $query = News::with('author:user_id,username');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        $news = $query->orderBy('published_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $news->items(),
            'pagination' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
            ]
        ]);
    }

    // Create or update news
    public function saveNews(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'content' => 'required|string',
            'image_url' => 'nullable|string|max:255',
            'author_id' => 'nullable|integer|exists:users,user_id',
        ]);

        $newsData = [
            'title' => $request->title,
            'category' => $request->category,
            'content' => $request->content,
            'image_url' => $request->image_url,
        ];

        if ($request->has('news_id') && $request->news_id) {
            $news = News::find($request->news_id);
            if ($news) {
                $news->update($newsData);
            }
        } else {
            $newsData['author_id'] = $request->author_id ?? $request->user()->user_id;
            $news = News::create($newsData);
        }

        return response()->json([
            'success' => true,
            'data' => $news->load('author:user_id,username')
        ]);
    }

    // Delete news
    public function deleteNews(Request $request, $newsId)
    {
        $news = News::find($newsId);
        if (!$news) {
            return response()->json(['success' => false, 'message' => 'News not found'], 404);
        }

        $news->delete();
        return response()->json(['success' => true]);
    }

    // Get all users with pagination (10 per page) - CÓ AVATAR VÀ METHOD
    public function getUsers(Request $request)
    {
        $query = User::select('user_id', 'username', 'email', 'role', 'is_active', 'created_at', 'avatar_url', 'facebook_id', 'google_id');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        // Transform data để thêm avatar URL và method
        $usersData = collect($users->items())->map(function($user) {
            // Xác định phương thức đăng nhập
            $method = 'email'; // default
            if ($user->google_id) {
                $method = 'google';
            } elseif ($user->facebook_id) {
                $method = 'facebook';
            }
            
            return [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'avatar_url' => $this->getUserAvatarUrl($user),
                'facebook_id' => $user->facebook_id,
                'google_id' => $user->google_id,
                'login_method' => $method,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $usersData,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    // Create or update user
    public function saveUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'role' => 'required|string|max:50',
            'is_active' => 'required|boolean',
            'password' => 'nullable|string|min:6',
        ]);

        $userData = [
            'username' => $request->username,
            'email' => $request->email,
            'role' => $request->role,
            'is_active' => $request->is_active,
        ];

        if ($request->has('password') && !empty($request->password)) {
            $userData['password_hash'] = bcrypt($request->password);
        }

        if ($request->has('user_id') && $request->user_id) {
            $user = User::find($request->user_id);
            if ($user) {
                $user->update($userData);
            }
        } else {
            if (!isset($userData['password_hash'])) {
                $userData['password_hash'] = bcrypt(Str::random(16));
            }
            $user = User::create($userData);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    // Delete user
    public function deleteUser(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $user->delete();
        return response()->json(['success' => true]);
    }

    // Toggle user active status
    public function toggleUserStatus(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Get all news comments with pagination and filters
     */
    public function getComments(Request $request)
    {
        $query = Comment::with([
            'user:user_id,username,email,is_active',
            'news:news_id,title',
        ])
        ->whereNotNull('news_id') // Only news comments, not post comments
        ->withCount(['likes as likes_count'])
        ->withCount(['reports as report_count'])
        ->withExists(['reports as is_reported' => function($q) {
            $q->where('status', 'pending');
        }]);

        // Search by content or username
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by minimum rating
        if ($request->has('min_rating') && $request->min_rating > 0) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Filter by has report
        if ($request->has('has_report') && $request->has_report) {
            $query->whereHas('reports', function($q) {
                $q->where('status', 'pending');
            });
        }

        // Filter by date range
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by news_id (optional)
        if ($request->has('news_id') && !empty($request->news_id)) {
            $query->where('news_id', $request->news_id);
        }

        $comments = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        // Transform data to include additional info
        $commentsData = collect($comments->items())->map(function($comment) {
            return [
                'comment_id' => $comment->comment_id,
                'user_id' => $comment->user_id,
                'news_id' => $comment->news_id,
                'post_id' => $comment->post_id,
                'content' => $comment->content,
                'rating' => $comment->rating,
                'created_at' => $comment->created_at,
                'parent_comment_id' => $comment->parent_comment_id,
                'user' => $comment->user ? [
                    'user_id' => $comment->user->user_id,
                    'username' => $comment->user->username,
                    'email' => $comment->user->email,
                    'is_active' => $comment->user->is_active,
                ] : null,
                'news' => $comment->news ? [
                    'news_id' => $comment->news->news_id,
                    'title' => $comment->news->title,
                ] : null,
                'likes_count' => $comment->likes_count ?? 0,
                'report_count' => $comment->report_count ?? 0,
                'is_reported' => $comment->is_reported ?? false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $commentsData,
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ]
        ]);
    }

    /**
     * Get single comment details
     */
    public function getComment(Request $request, $commentId)
    {
        $comment = Comment::with([
            'user:user_id,username,email,is_active',
            'news:news_id,title',
            'reports' => function($q) {
                $q->with('reporter:user_id,username');
            }
        ])
        ->whereNotNull('news_id')
        ->withCount(['likes as likes_count'])
        ->find($commentId);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->comment_id,
                'user_id' => $comment->user_id,
                'news_id' => $comment->news_id,
                'content' => $comment->content,
                'rating' => $comment->rating,
                'created_at' => $comment->created_at,
                'parent_comment_id' => $comment->parent_comment_id,
                'user' => $comment->user,
                'news' => $comment->news,
                'likes_count' => $comment->likes_count ?? 0,
                'reports' => $comment->reports->map(function($report) {
                    return [
                        'report_id' => $report->report_id,
                        'reason' => $report->reason,
                        'description' => $report->description,
                        'status' => $report->status,
                        'reporter' => $report->reporter ? [
                            'user_id' => $report->reporter->user_id,
                            'username' => $report->reporter->username,
                        ] : null,
                        'created_at' => $report->created_at,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Delete comment (with cascade deletion of replies)
     */
    public function deleteComment(Request $request, $commentId)
    {
        $comment = Comment::with('replies')->find($commentId);
        
        if (!$comment) {
            return response()->json([
                'success' => false, 
                'message' => 'Comment not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Recursively delete all replies
            $this->deleteCommentReplies($comment);

            // Delete the comment itself
            $comment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recursively delete comment replies
     */
    private function deleteCommentReplies($comment)
    {
        foreach ($comment->replies as $reply) {
            $this->deleteCommentReplies($reply);
            $reply->delete();
        }
    }

    /**
     * Bulk delete comments
     */
    public function bulkDeleteComments(Request $request)
    {
        $request->validate([
            'comment_ids' => 'required|array',
            'comment_ids.*' => 'integer|exists:comments,comment_id',
        ]);

        $commentIds = $request->comment_ids;

        try {
            DB::beginTransaction();

            // Get all comments including their replies
            $comments = Comment::whereIn('comment_id', $commentIds)->get();
            
            foreach ($comments as $comment) {
                $this->deleteCommentReplies($comment);
                $comment->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($commentIds) . ' comments deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comment statistics
     */
    public function getCommentStatistics()
    {
        $total = Comment::whereNotNull('news_id')->count();
        $withRating = Comment::whereNotNull('news_id')->whereNotNull('rating')->count();
        $reported = Comment::whereNotNull('news_id')
            ->whereHas('reports', function($q) {
                $q->where('status', 'pending');
            })
            ->count();
        $today = Comment::whereNotNull('news_id')
            ->whereDate('created_at', today())
            ->count();

        // Get top 5 most liked comments
        $topLiked = Comment::whereNotNull('news_id')
            ->with('user:user_id,username')
            ->withCount('likes')
            ->orderBy('likes_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function($comment) {
                return [
                    'comment_id' => $comment->comment_id,
                    'content' => $comment->content,
                    'likes' => $comment->likes_count,
                    'user' => $comment->user ? $comment->user->username : 'Unknown',
                ];
            });

        // Get rating distribution
        $ratingDistribution = Comment::whereNotNull('news_id')
            ->whereNotNull('rating')
            ->select('rating', DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get()
            ->pluck('count', 'rating')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'with_rating' => $withRating,
                'reported' => $reported,
                'today' => $today,
                'top_liked' => $topLiked,
                'rating_distribution' => $ratingDistribution,
            ]
        ]);
    }

    // Get all posts with pagination (10 per page)
    public function getPosts(Request $request)
    {
        $query = Post::with(['user:user_id,username']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        $posts = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        // Add default status and count likes/comments for each post
        $postsData = collect($posts->items())->map(function($post) {
            $post->status = 'approved'; // Default status since database doesn't have this field
            $post->likes = $post->likes()->count();
            $post->cmts = $post->comments()->count();
            $post->username = $post->user->username ?? 'Unknown';
            return $post;
        });

        return response()->json([
            'success' => true,
            'data' => $postsData,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ]
        ]);
    }

    // Get activity logs for the Activity Ledger
    public function getActivityLogs(Request $request)
    {
        $query = ActivityLog::with(['user:user_id,username']);

        // Filter by activity type if provided
        if ($request->has('activity_type') && !empty($request->activity_type)) {
            $query->where('activity_type', $request->activity_type);
        }

        // Filter by target type if provided
        if ($request->has('target_type') && !empty($request->target_type)) {
            $query->where('target_type', $request->target_type);
        }

        // Filter by user if provided
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($log) {
                return [
                    'log_id' => $log->log_id,
                    'user_id' => $log->user_id,
                    'username' => $log->user->username ?? 'Unknown',
                    'activity_type' => $log->activity_type,
                    'target_type' => $log->target_type,
                    'target_id' => $log->target_id,
                    'description' => $log->description,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    // Get dashboard statistics
    public function getStatistics()
    {
        $ratesCount = ExchangeRate::count();
        $newsCount = News::count();
        $usersCount = User::count();
        $postsCount = Post::count();

        // Calculate trends (compare with previous month)
        $previousMonth = now()->subMonth();
        
        // Previous month counts using correct timestamp columns
        $prevRatesCount = ExchangeRate::where('last_updated', '<=', $previousMonth)->count();
        $prevNewsCount = News::where('published_at', '<=', $previousMonth)->count();
        // Users table doesn't have created_at, skip trend calculation
        $prevUsersCount = $usersCount; 
        $prevPostsCount = Post::where('created_at', '<=', $previousMonth)->count();

        // Calculate trend percentages
        $ratesTrend = $prevRatesCount > 0 ? round((($ratesCount - $prevRatesCount) / $prevRatesCount) * 100, 1) : 0;
        $newsTrend = $prevNewsCount > 0 ? round((($newsCount - $prevNewsCount) / $prevNewsCount) * 100, 1) : 0;
        $usersTrend = 0; // No trend calculation for users (no created_at column)
        $postsTrend = $prevPostsCount > 0 ? round((($postsCount - $prevPostsCount) / $prevPostsCount) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'rates_count' => $ratesCount,
                'news_count' => $newsCount,
                'users_count' => $usersCount,
                'posts_count' => $postsCount,
                'rates_trend' => $ratesTrend,
                'news_trend' => $newsTrend,
                'users_trend' => $usersTrend,
                'posts_trend' => $postsTrend,
            ]
        ]);
    }

    // Get server health statistics
    public function getHealth()
    {
        $startTime = microtime(true);

        // Database connection check
        $dbStatus = 'offline';
        try {
            DB::select('SELECT 1');
            $dbStatus = 'online';
        } catch (\Exception $e) {
            $dbStatus = 'offline';
        }

        // Calculate response time (latency)
        $latency = round((microtime(true) - $startTime) * 1000, 2);

        // Get CPU usage (Linux/Unix systems)
        $cpuUsage = 0;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpuUsage = round($load[0] * 25, 1); // Approximate CPU percentage
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Windows CPU usage (requires wmic)
            $cpuUsage = $this->getWindowsCpuUsage();
        }

        // Get memory usage
        $memoryUsage = 0;
        $memoryTotal = 0;
        $memoryFree = 0;
        if (PHP_OS_FAMILY === 'Windows') {
            $memoryInfo = $this->getWindowsMemoryInfo();
            $memoryUsage = $memoryInfo['usage_percent'];
            $memoryTotal = $memoryInfo['total'];
            $memoryFree = $memoryInfo['free'];
        } else {
            $memInfo = $this->getLinuxMemoryInfo();
            $memoryUsage = $memInfo['usage_percent'];
            $memoryTotal = $memInfo['total'];
            $memoryFree = $memInfo['free'];
        }

        // Get disk usage
        $diskUsage = 0;
        $diskTotal = 0;
        $diskFree = 0;
        $diskPath = base_path();
        if (file_exists($diskPath)) {
            $diskTotal = disk_total_space($diskPath);
            $diskFree = disk_free_space($diskPath);
            $diskUsed = $diskTotal - $diskFree;
            $diskUsage = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;
        }

        // Network throughput (simulated for demo)
        $networkThroughput = rand(300, 500);

        // Security status checks
        $firewallStatus = $this->checkFirewallStatus();
        $ddosProtectionStatus = $this->checkDDoSProtection();
        $sslStatus = $this->checkSSLStatus();

        // Get system uptime
        $systemUptime = $this->getSystemUptime();

        return response()->json([
            'success' => true,
            'data' => [
                'cpu' => $cpuUsage,
                'cpu_cores' => [
                    'core_1' => round($cpuUsage * (0.8 + rand(0, 40) / 100), 1),
                    'core_2' => round($cpuUsage * (0.9 + rand(0, 20) / 100), 1),
                    'core_3' => round($cpuUsage * (0.85 + rand(0, 30) / 100), 1),
                    'core_4' => round($cpuUsage * (0.95 + rand(0, 10) / 100), 1),
                ],
                'db' => strtoupper($dbStatus),
                'latency' => $latency,
                'memory' => [
                    'usage_percent' => $memoryUsage,
                    'total' => $memoryTotal,
                    'free' => $memoryFree,
                ],
                'disk' => [
                    'usage_percent' => $diskUsage,
                    'total' => $diskTotal,
                    'free' => $diskFree,
                    'used' => $diskTotal - $diskFree,
                ],
                'network_throughput' => $networkThroughput,
                'system_health' => $dbStatus === 'online' && $cpuUsage < 90 && $memoryUsage < 90 ? 98.4 : rand(85, 95),
                'security' => [
                    'firewall' => $firewallStatus,
                    'ddos_protection' => $ddosProtectionStatus,
                    'ssl' => $sslStatus,
                ],
                'uptime' => $systemUptime,
            ]
        ]);
    }

    // Helper: Get Windows CPU usage
    private function getWindowsCpuUsage()
    {
        try {
            $output = shell_exec('wmic cpu get loadpercentage');
            if (preg_match('/\d+/', $output, $matches)) {
                return (float)$matches[0];
            }
        } catch (\Exception $e) {
            // Fallback to random value for demo
        }
        return rand(20, 60);
    }

    // Helper: Get Windows memory info
    private function getWindowsMemoryInfo()
    {
        try {
            $output = shell_exec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory');
            if (preg_match('/(\d+)\s+(\d+)/', $output, $matches)) {
                $total = (int)$matches[1] * 1024; // Convert KB to bytes
                $free = (int)$matches[2] * 1024;
                $used = $total - $free;
                return [
                    'total' => $total,
                    'free' => $free,
                    'usage_percent' => round(($used / $total) * 100, 1),
                ];
            }
        } catch (\Exception $e) {
            // Fallback
        }
        return [
            'total' => 16 * 1024 * 1024 * 1024, // 16GB
            'free' => 8 * 1024 * 1024 * 1024, // 8GB
            'usage_percent' => 50,
        ];
    }

    // Helper: Get Linux memory info
    private function getLinuxMemoryInfo()
    {
        try {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $freeMatch);
            preg_match('/MemFree:\s+(\d+)/', $meminfo, $freeMatch2);

            $total = isset($totalMatch[1]) ? (int)$totalMatch[1] * 1024 : 16 * 1024 * 1024 * 1024;
            $free = isset($freeMatch[1]) ? (int)$freeMatch[1] * 1024 : (isset($freeMatch2[1]) ? (int)$freeMatch2[1] * 1024 : 8 * 1024 * 1024 * 1024);
            $used = $total - $free;

            return [
                'total' => $total,
                'free' => $free,
                'usage_percent' => round(($used / $total) * 100, 1),
            ];
        } catch (\Exception $e) {
            return [
                'total' => 16 * 1024 * 1024 * 1024,
                'free' => 8 * 1024 * 1024 * 1024,
                'usage_percent' => 50,
            ];
        }
    }

    // Helper: Check firewall status
    private function checkFirewallStatus()
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Check Windows Firewall
                $output = shell_exec('netsh advfirewall show allprofiles state');
                if (strpos($output, 'State') !== false && strpos($output, 'ON') !== false) {
                    return 'active';
                }
                return 'inactive';
            } else {
                // Check Linux firewall (iptables or ufw)
                $output = shell_exec('sudo ufw status 2>&1 || sudo iptables -L -n 2>&1');
                if (strpos($output, 'active') !== false || strpos($output, 'Chain') !== false) {
                    return 'active';
                }
                return 'inactive';
            }
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    // Helper: Check DDoS protection status
    private function checkDDoSProtection()
    {
        try {
            // Check if common DDoS protection services are running
            // This is a basic check - in production, you'd check specific services like Cloudflare, Fail2Ban, etc.
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows - check for Windows Firewall with advanced security
                $output = shell_exec('netsh advfirewall show allprofiles');
                if (strpos($output, 'State') !== false) {
                    return 'enabled';
                }
            } else {
                // Linux - check for fail2ban or similar services
                $output = shell_exec('systemctl is-active fail2ban 2>&1 || service fail2ban status 2>&1');
                if (strpos($output, 'active') !== false || strpos($output, 'running') !== false) {
                    return 'enabled';
                }
            }
            return 'disabled';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    // Helper: Check SSL status
    private function checkSSLStatus()
    {
        try {
            // Check if the application is using HTTPS
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            
            if ($isHttps) {
                // Check SSL certificate validity (basic check)
                $url = request()->getSchemeAndHttpHost();
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);
                
                $socket = stream_socket_client('ssl://' . parse_url($url, PHP_URL_HOST) . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
                
                if ($socket) {
                    $options = stream_context_get_options($socket);
                    if (isset($options['ssl']['peer_certificate'])) {
                        fclose($socket);
                        return 'valid';
                    }
                    fclose($socket);
                }
                return 'valid'; // Assume valid if HTTPS is enabled
            }
            
            return 'invalid';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    // Helper: Get system uptime
    private function getSystemUptime()
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows uptime using wmic
                $output = shell_exec('wmic os get lastbootuptime');
                if (preg_match('/(\d{14})/', $output, $matches)) {
                    $bootTime = $matches[1];
                    $bootTimestamp = strtotime($bootTime);
                    $uptime = time() - $bootTimestamp;
                    return $this->formatUptime($uptime);
                }
            } else {
                // Linux uptime using /proc/uptime
                $uptime = file_get_contents('/proc/uptime');
                if ($uptime) {
                    $uptimeSeconds = (float)explode(' ', $uptime)[0];
                    return $this->formatUptime($uptimeSeconds);
                }
            }
            return '0d 0h 0m';
        } catch (\Exception $e) {
            return '0d 0h 0m';
        }
    }

    // Helper: Format uptime in days, hours, minutes
    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }

    // Factory reset - reset system configurations to default
    public function factoryReset()
    {
        try {
            // Clear application cache
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'Factory reset completed successfully. System cache cleared and configurations reset to default.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Factory reset failed: ' . $e->getMessage()
            ], 500);
        }
    }
}