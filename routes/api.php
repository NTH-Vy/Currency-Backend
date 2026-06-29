<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\BroadcastNoticeController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserActivityController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route kiểm tra trạng thái bảo trì - LUÔN HOẠT ĐỘNG
Route::get('/maintenance-status', [SettingController::class, 'getMaintenanceStatus']);

// Public Routes
Route::middleware('maintenance')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/trending', [NewsController::class, 'trending']);
    Route::get('/news/{id}', [NewsController::class, 'show']);
    Route::get('/news/{id}/related', [NewsController::class, 'related']);
    Route::get('/community', [CommunityController::class, 'index']);
    Route::get('/broadcast-notices', [BroadcastNoticeController::class, 'index']);
    Route::get('/rates/current', [CurrencyController::class, 'getCurrentRates']);
    Route::get('/rates/historical', [CurrencyController::class, 'getHistoricalRates']);
    Route::get('/rates/market-matrix', [CurrencyController::class, 'getMarketMatrix']);
    Route::get('/rates/ticker', [CurrencyController::class, 'getTickerRates']);
    Route::get('/rates/top-movers', [CurrencyController::class, 'getTopMovers']);
    Route::get('/rates/market-pulse', [CurrencyController::class, 'getMarketPulse']);
    Route::get('/rates/strength', [CurrencyController::class, 'getCurrencyStrength']);
    Route::get('/currencies', [CurrencyController::class, 'getCurrencies']);
    Route::get('/calendar/events', [CurrencyController::class, 'getEconomicCalendar']);
});

// Login routes should always be accessible (even during maintenance for admins)
Route::post('/login', [AuthController::class, 'login']);
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/auth/facebook/redirect', [AuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);

// Public endpoint to check maintenance status (no auth required)
Route::get('/maintenance-status', [SettingController::class, 'getMaintenanceStatus']);

// Public Routes - Broadcast Notices
Route::middleware('maintenance')->group(function () {
    Route::get('/broadcast-notices', [BroadcastNoticeController::class, 'index']);

    // Public Routes - Currency rates (no auth required)
    Route::get('/rates/current', [CurrencyController::class, 'getCurrentRates']);
    Route::get('/rates/historical', [CurrencyController::class, 'getHistoricalRates']);
    Route::get('/rates/market-matrix', [CurrencyController::class, 'getMarketMatrix']);
    Route::get('/rates/ticker', [CurrencyController::class, 'getTickerRates']);
    Route::get('/rates/top-movers', [CurrencyController::class, 'getTopMovers']);
    Route::get('/rates/market-pulse', [CurrencyController::class, 'getMarketPulse']);
    Route::get('/rates/strength', [CurrencyController::class, 'getCurrencyStrength']);
    Route::get('/currencies', [CurrencyController::class, 'getCurrencies']);
    Route::get('/calendar/events', [CurrencyController::class, 'getEconomicCalendar']);

    // Public Routes - Support Tickets (submit without auth)
    Route::post('/support-tickets', [SupportTicketController::class, 'submit']);
});

// Protected Routes (Yêu cầu Token)
Route::middleware(['auth:sanctum', 'maintenance'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/user/profile', [AuthController::class, 'getProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user/ban-status', [ReportController::class, 'checkBanStatus']);

    // Avatar upload
    Route::post('/user/avatar', [UploadController::class, 'uploadAvatar']);
    Route::delete('/user/avatar', [UploadController::class, 'deleteAvatar']);

    // Report comment (requires authentication)
    Route::post('/comments/{commentId}/report', [ReportController::class, 'store']);

    Route::get('/user/all-activities', [UserActivityController::class, 'getAllActivities']);
    
    // AI Summary
    Route::post('/ai/summary', [AIController::class, 'generateSummary']);

    // Tiền tệ
    Route::post('/convert', [CurrencyController::class, 'convert']);
    Route::post('/convert-quote', [RateController::class, 'quote']);
    // Refresh rates from live API
    Route::post('/refresh-rates', [CurrencyController::class, 'refreshAllRates']);
    Route::get('/history', [CurrencyController::class, 'history']);
    Route::get('/history-paginated', [HistoryController::class, 'index']);
    Route::delete('/history/{id}', [CurrencyController::class, 'deleteHistory']);
    Route::delete('/history', [CurrencyController::class, 'clearHistory']);
    Route::post('/currencies/favorite', [CurrencyController::class, 'toggleFavoritePair']);
    Route::get('/currencies/favorites', [CurrencyController::class, 'getFavoritePairs']);

    // Rate Alerts
    Route::post('/rates/alerts', [CurrencyController::class, 'createRateAlert']);
    Route::get('/rates/alerts', [CurrencyController::class, 'getRateAlerts']);
    Route::delete('/rates/alerts/{alertId}', [CurrencyController::class, 'deleteRateAlert']);

    // Tin tức & Bình luận & Yêu thích
    Route::post('/news/{id}/comment', [NewsController::class, 'postComment']);
    Route::put('/news/{id}/comment/{commentId}', [NewsController::class, 'updateComment']);
    Route::delete('/news/{id}/comment/{commentId}', [NewsController::class, 'deleteComment']);
    Route::post('/news/{id}/comment/{commentId}/like', [NewsController::class, 'likeComment']);
    Route::post('/news/{id}/favorite', [NewsController::class, 'toggleFavorite']);
    Route::get('/user/comments', [NewsController::class, 'getUserComments']);
    Route::get('/news/{id}/comments', [NewsController::class, 'getComments']);

    // Cộng đồng (Community)
    Route::get('/community/{id}', [CommunityController::class, 'show']);
    Route::post('/community/post', [CommunityController::class, 'store']);
    Route::put('/community/post/{id}', [CommunityController::class, 'update']);
    Route::delete('/community/post/{id}', [CommunityController::class, 'destroy']);
    Route::post('/community/post/{id}/like', [CommunityController::class, 'toggleLike']);
    Route::post('/community/post/{postId}/comment', [CommunityController::class, 'postComment']);
    Route::delete('/community/post/{postId}/comment/{commentId}', [CommunityController::class, 'deleteComment']);
    Route::post('/community/post/{postId}/comment/{commentId}/like', [CommunityController::class, 'likeComment']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    //Upload avatar
    Route::post('/upload/avatar', [UploadController::class, 'uploadAvatar']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::post('/upload/user', [UploadController::class, 'uploadUser']);

    // User Support Tickets
    Route::get('/support-tickets/my', [SupportTicketController::class, 'myTickets']);
});

// Admin Routes (Yêu cầu Token và Role Admin)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/check', function (Request $request) {
        return response()->json(['message' => 'Admin access granted', 'user' => $request->user()]);
    });

    // Admin - Exchange Rates
    Route::get('/admin/rates', [AdminController::class, 'getRates']);
    Route::post('/admin/rates', [AdminController::class, 'saveRate']);
    Route::delete('/admin/rates/{rateId}', [AdminController::class, 'deleteRate']);

    // Admin - News
    Route::get('/admin/news', [AdminController::class, 'getNews']);
    Route::post('/admin/news', [AdminController::class, 'saveNews']);
    Route::delete('/admin/news/{newsId}', [AdminController::class, 'deleteNews']);

    // Admin - Reports
    Route::get('/admin/reports', [ReportController::class, 'index']);
    Route::get('/admin/reports/{reportId}', [ReportController::class, 'show']);
    Route::post('/admin/reports/{reportId}/review', [ReportController::class, 'review']);
    Route::post('/admin/reports/{reportId}/reject', [ReportController::class, 'reject']);
    Route::delete('/admin/reports/{reportId}', [ReportController::class, 'destroy']);
    Route::get('/admin/reports/statistics', [ReportController::class, 'statistics']);

    // Admin - Upload - News
    Route::post('/admin/upload/news', [UploadController::class, 'uploadNews']);

    // Admin - Users
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
    Route::post('/admin/users', [AdminController::class, 'saveUser']);
    Route::delete('/admin/users/{userId}', [AdminController::class, 'deleteUser']);
    Route::patch('/admin/users/{userId}/toggle-status', [AdminController::class, 'toggleUserStatus']);

    // Admin - Comments (NEWS COMMENTS ONLY)
    Route::get('/admin/comments', [AdminController::class, 'getComments']);
    Route::get('/admin/comments/{commentId}', [AdminController::class, 'getComment']);
    Route::delete('/admin/comments/{commentId}', [AdminController::class, 'deleteComment']);
    Route::post('/admin/comments/bulk-delete', [AdminController::class, 'bulkDeleteComments']);
    Route::get('/admin/comments/statistics', [AdminController::class, 'getCommentStatistics']);

    // Admin - Posts
    Route::get('/admin/posts', [AdminController::class, 'getPosts']);

    // Admin - Activity Logs
    Route::get('/admin/activity-logs', [AdminController::class, 'getActivityLogs']);

    // Admin - Dashboard Statistics
    Route::get('/admin/statistics', [AdminController::class, 'getStatistics']);

    // Admin - Server Health
    Route::get('/admin/health', [AdminController::class, 'getHealth']);

    // Admin - Factory Reset
    Route::post('/admin/factory-reset', [AdminController::class, 'factoryReset']);

    // Admin - Broadcast Notices
    Route::post('/admin/broadcast-notices', [BroadcastNoticeController::class, 'store']);
    Route::delete('/admin/broadcast-notices/{noticeId}', [BroadcastNoticeController::class, 'destroy']);
    Route::patch('/admin/broadcast-notices/{noticeId}/toggle', [BroadcastNoticeController::class, 'toggleStatus']);

    // Admin - Settings
    Route::get('/admin/settings', [SettingController::class, 'index']);
    Route::post('/admin/settings', [SettingController::class, 'store']);

    // Admin - Support Tickets
    Route::get('/admin/support-tickets', [SupportTicketController::class, 'index']);
    Route::get('/admin/support-tickets/{ticketId}', [SupportTicketController::class, 'show']);
    Route::post('/admin/support-tickets/{ticketId}/respond', [SupportTicketController::class, 'respond']);
    Route::put('/admin/support-tickets/{ticketId}/status', [SupportTicketController::class, 'updateStatus']);
    Route::delete('/admin/support-tickets/{ticketId}', [SupportTicketController::class, 'destroy']);
    Route::get('/admin/support-tickets/statistics', [SupportTicketController::class, 'statistics']);
});