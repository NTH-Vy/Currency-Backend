<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Report;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Người dùng báo cáo một comment
     */
    public function store(Request $request, $commentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|in:spam,offensive,harassment,misinformation,hate_speech,inappropriate_content,other',
                'description' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized - Please login to report comments'], 401);
            }

            // Kiểm tra comment tồn tại
            $comment = Comment::find($commentId);
            if (!$comment) {
                return response()->json(['message' => 'Comment not found'], 404);
            }

            // Không cho tự báo cáo comment của mình
            if ($comment->user_id === $user->user_id) {
                return response()->json([
                    'message' => 'Bạn không thể báo cáo bình luận của chính mình'
                ], 403);
            }

            // Kiểm tra đã báo cáo comment này chưa (pending)
            $existingReport = Report::where('reporter_id', $user->user_id)
                ->where('comment_id', $commentId)
                ->where('status', 'pending')
                ->first();

            if ($existingReport) {
                return response()->json([
                    'message' => 'Bạn đã báo cáo bình luận này, vui lòng chờ xử lý'
                ], 409);
            }

            // Tạo báo cáo
            $report = Report::create([
                'reporter_id' => $user->user_id,
                'comment_id' => $commentId,
                'reason' => $request->reason,
                'description' => $request->description,
                'status' => 'pending'
            ]);

            // Load thông tin
            $report->load(['reporter', 'comment.user']);

            return response()->json([
                'message' => 'Báo cáo đã được gửi thành công!',
                'report' => $report
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin lấy danh sách báo cáo
     */
    public function index(Request $request)
    {
        $query = Report::with(['reporter', 'comment.user', 'reviewer']);

        // Filter theo status
        if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected', 'all'])) {
            if ($request->status !== 'all') {
                $query->where('status', $request->status);
            }
        } else {
            $query->where('status', 'pending');
        }

        // Filter theo reason
        if ($request->has('reason') && in_array($request->reason, array_keys(Report::REASONS))) {
            $query->where('reason', $request->reason);
        }

        // Search by reporter, comment content, or description
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('admin_note', 'like', "%{$search}%")
                  ->orWhereHas('reporter', function ($rq) use ($search) {
                      $rq->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('comment', function ($cq) use ($search) {
                      $cq->where('content', 'like', "%{$search}%");
                  });
            });
        }

        // Date range filter
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        // Build stats based on the same filtered query (global counts for current filters)
        $statsQuery = clone $query;
        $stats = [
            'total_reports' => $statsQuery->count(),
            'pending_reports' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved_reports' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected_reports' => (clone $statsQuery)->where('status', 'rejected')->count(),
        ];

        $arr = $reports->toArray();
        $arr['stats'] = $stats;

        return response()->json($arr);
    }

    /**
     * Admin xem chi tiết báo cáo
     */
    public function show($reportId)
    {
        $report = Report::with(['reporter', 'comment.user', 'reviewer'])->find($reportId);
        
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        return response()->json($report);
    }

    /**
     * Admin duyệt báo cáo
     */
    public function review(Request $request, $reportId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:warning,delete_comment,temporary_ban,permanent_ban,none',
            'ban_duration_days' => 'sometimes|required_if:action,temporary_ban|nullable|integer|min:1|max:365',
            'admin_note' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $report = Report::with(['comment.user'])->find($reportId);
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        if (!$report->comment || !$report->comment->user) {
            return response()->json(['message' => 'Comment or user not found for this report'], 404);
        }

        if ($report->status !== 'pending') {
            return response()->json([
                'message' => 'Báo cáo này đã được xử lý trước đó'
            ], 400);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $action = $request->action;
        $banDurationDays = $request->ban_duration_days;
        $adminNote = $request->admin_note;

        DB::beginTransaction();

        try {
            // Cập nhật report
            $report->status = 'approved';
            $report->reviewed_by = $user->user_id;
            $report->reviewed_at = now();
            $report->action_taken = $action;
            $report->admin_note = $adminNote;

            if ($action === 'temporary_ban' && $banDurationDays) {
                $report->ban_duration_days = $banDurationDays;
                $report->ban_until = now()->addDays($banDurationDays);
            }

            $report->save();

            // Xử lý hành động
            $targetUser = $report->comment->user;
            $comment = $report->comment;

            switch ($action) {
                case 'delete_comment':
                    // Tạo notification cho user bị tố cáo
                    try {
                        Notification::create([
                            'user_id' => $targetUser->user_id,
                            'type' => 'report',
                            'actor_id' => $user->user_id,
                            'post_id' => null,
                            'comment_id' => $comment->comment_id,
                            'is_read' => 0,
                        ]);
                    } catch (\Exception $e) {
                        // Log error but continue
                        \Log::error('Failed to create notification: ' . $e->getMessage());
                    }
                    
                    $comment->delete();
                    break;

                case 'temporary_ban':
                    $targetUser->banned_until = now()->addDays($banDurationDays);
                    $targetUser->ban_reason = "Vi phạm quy định bình luận: " . Report::REASONS[$report->reason];
                    $targetUser->save();
                    
                    // Tạo notification cho user bị ban
                    try {
                        Notification::create([
                            'user_id' => $targetUser->user_id,
                            'type' => 'ban',
                            'actor_id' => $user->user_id,
                            'post_id' => null,
                            'comment_id' => $comment->comment_id,
                            'is_read' => 0,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to create notification: ' . $e->getMessage());
                    }
                    
                    // Xóa comment vi phạm
                    $comment->delete();
                    break;

                case 'permanent_ban':
                    $targetUser->banned_until = now()->addYears(100);
                    $targetUser->ban_reason = "Vi phạm nghiêm trọng quy định bình luận: " . Report::REASONS[$report->reason];
                    $targetUser->save();
                    
                    // Tạo notification cho user bị ban vĩnh viễn
                    try {
                        Notification::create([
                            'user_id' => $targetUser->user_id,
                            'type' => 'ban',
                            'actor_id' => $user->user_id,
                            'post_id' => null,
                            'comment_id' => $comment->comment_id,
                            'is_read' => 0,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to create notification: ' . $e->getMessage());
                    }
                    
                    // Xóa comment vi phạm
                    $comment->delete();
                    break;

                case 'warning':
                    // Tạo notification cảnh cáo cho user
                    try {
                        Notification::create([
                            'user_id' => $targetUser->user_id,
                            'type' => 'warning',
                            'actor_id' => $user->user_id,
                            'post_id' => null,
                            'comment_id' => $comment->comment_id,
                            'is_read' => 0,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to create notification: ' . $e->getMessage());
                    }
                    break;

                case 'none':
                    // Tạo notification cho user bị tố cáo (không có hành động)
                    try {
                        Notification::create([
                            'user_id' => $targetUser->user_id,
                            'type' => 'report',
                            'actor_id' => $user->user_id,
                            'post_id' => null,
                            'comment_id' => $comment->comment_id,
                            'is_read' => 0,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed to create notification: ' . $e->getMessage());
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'message' => 'Báo cáo đã được xử lý thành công',
                'report' => $report->load(['reporter', 'comment.user', 'reviewer'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Có lỗi xảy ra khi xử lý báo cáo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin từ chối báo cáo
     */
    public function reject(Request $request, $reportId)
    {
        $report = Report::find($reportId);
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        if ($report->status !== 'pending') {
            return response()->json([
                'message' => 'Báo cáo này đã được xử lý trước đó'
            ], 400);
        }

        $user = $request->user();

        $report->status = 'rejected';
        $report->reviewed_by = $user->user_id;
        $report->reviewed_at = now();
        $report->admin_note = $request->admin_note ?? 'Từ chối báo cáo';
        $report->save();

        return response()->json([
            'message' => 'Đã từ chối báo cáo',
            'report' => $report->load(['reporter', 'comment.user', 'reviewer'])
        ]);
    }

    /**
     * Admin xóa báo cáo
     */
    public function destroy($reportId)
    {
        $report = Report::find($reportId);
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $report->delete();

        return response()->json([
            'message' => 'Đã xóa báo cáo'
        ]);
    }

    /**
     * Lấy thống kê báo cáo cho admin
     */
    public function statistics()
    {
        $stats = [
            'total_reports' => Report::count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'approved_reports' => Report::where('status', 'approved')->count(),
            'rejected_reports' => Report::where('status', 'rejected')->count(),
            'reports_by_reason' => Report::select('reason', DB::raw('count(*) as count'))
                ->groupBy('reason')
                ->get()
                ->map(function ($item) {
                    return [
                        'reason' => $item->reason,
                        'label' => Report::REASONS[$item->reason] ?? $item->reason,
                        'count' => $item->count
                    ];
                }),
            'reports_by_action' => Report::where('status', 'approved')
                ->select('action_taken', DB::raw('count(*) as count'))
                ->groupBy('action_taken')
                ->get()
                ->map(function ($item) {
                    return [
                        'action' => $item->action_taken,
                        'label' => Report::ACTIONS[$item->action_taken] ?? $item->action_taken,
                        'count' => $item->count
                    ];
                }),
            'recent_reports' => Report::with(['reporter', 'comment.user'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * Kiểm tra user có bị cấm comment không
     */
    public function checkBanStatus(Request $request)
    {
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
}