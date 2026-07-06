<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTicketController extends Controller
{
    // Public endpoint - submit a support ticket
    public function submit(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $userId = null;
        if (Auth::check()) {
            $userId = Auth::id();
        }

        $ticket = SupportTicket::create([
            'user_id' => $userId,
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // Create notifications for all admin users
        $admins = User::where('role', 'admin')->orWhere('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->user_id,
                'type' => 'reply',
                'actor_id' => $userId ?? 0,
                'actor_username' => $request->name,
                'post_id' => $ticket->ticket_id,
                'comment_id' => null,
                'comment_content' => "New support ticket #{$ticket->ticket_id} from {$request->name}: " . ($request->subject ?: 'Infrastructure Request'),
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Support ticket submitted successfully',
            'ticket' => $ticket
        ], 201);
    }

    /**
     * ⭐ Admin endpoint - get all tickets with pagination
     * Supports: per_page (default 10), page, status, priority, search
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with([
            'user' => function($q) {
                $q->select('user_id', 'username', 'email', 'avatar_url', 'facebook_id', 'google_id');
            }, 
            'admin'
        ]);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Filter by date range
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // ⭐ Get per_page from request, default 10
        $perPage = $request->input('per_page', 10);
        
        // ⭐ Limit max per_page to 100 to prevent abuse
        if ($perPage > 100) {
            $perPage = 100;
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Compute stats using the same filtered query so stats reflect filters (global totals)
        $statsQuery = clone $query;
        $stats = [
            'total' => $statsQuery->count(),
            'open' => (clone $statsQuery)->where('status', 'open')->count(),
            'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
            'resolved' => (clone $statsQuery)->where('status', 'resolved')->count(),
            'closed' => (clone $statsQuery)->where('status', 'closed')->count(),
            'high_priority' => (clone $statsQuery)->whereIn('priority', ['high', 'urgent'])->count(),
        ];

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'stats' => $stats,
        ]);
    }

    // Admin endpoint - get single ticket
    public function show($ticketId)
    {
        $ticket = SupportTicket::with([
            'user' => function($q) {
                $q->select('user_id', 'username', 'email', 'avatar_url', 'facebook_id', 'google_id');
            }, 
            'admin'
        ])->findOrFail($ticketId);

        return response()->json([
            'success' => true,
            'ticket' => $ticket
        ]);
    }

    /**
     * ⭐ Admin endpoint - respond to ticket với notification cải tiến
     */
    public function respond(Request $request, $ticketId)
    {
        $request->validate([
            'response' => 'required|string|min:1|max:5000',
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($ticketId);
        $admin = Auth::user();

        $ticket->update([
            'admin_response' => $request->response,
            'status' => $request->status,
            'admin_id' => $admin->user_id,
            'responded_at' => now(),
        ]);

        // ⭐ Tạo thông báo cho người dùng với nội dung chi tiết
        $userId = $ticket->user_id;
        
        // Nếu không có user_id, thử tìm user bằng email
        if (!$userId && $ticket->email) {
            $user = User::where('email', $ticket->email)->first();
            if ($user) {
                $userId = $user->user_id;
            }
        }
        
        if ($userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'reply',
                'actor_id' => $admin->user_id,
                'actor_username' => $admin->username,
                'post_id' => $ticket->ticket_id, // ⭐ Lưu ticket_id để user click vào
                'comment_id' => null,
                'comment_content' => $request->response, // ⭐ Lưu nội dung phản hồi
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }

        // Tạo thông báo cho các admin khác
        $admins = User::where('role', 'admin')->orWhere('role', 'ADMIN')
            ->where('user_id', '!=', $admin->user_id)
            ->get();
        foreach ($admins as $adminUser) {
            Notification::create([
                'user_id' => $adminUser->user_id,
                'type' => 'reply',
                'actor_id' => $admin->user_id,
                'actor_username' => $admin->username,
                'post_id' => $ticket->ticket_id,
                'comment_id' => null,
                'comment_content' => "Admin @{$admin->username} responded to ticket #{$ticket->ticket_id}",
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Response submitted successfully',
            'ticket' => $ticket->load(['user' => function($q) {
                $q->select('user_id', 'username', 'email', 'avatar_url', 'facebook_id', 'google_id');
            }, 'admin'])
        ]);
    }

    // Admin endpoint - update ticket status
    public function updateStatus(Request $request, $ticketId)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $ticket = SupportTicket::findOrFail($ticketId);
        $admin = Auth::user();

        $ticket->update([
            'status' => $request->status,
            'priority' => $request->priority ?? $ticket->priority,
        ]);

        // Notify user about status change if they have an account
        if ($ticket->user_id) {
            $statusLabels = [
                'open' => 'Đang mở',
                'in_progress' => 'Đang xử lý',
                'resolved' => 'Đã giải quyết',
                'closed' => 'Đã đóng'
            ];
            
            Notification::create([
                'user_id' => $ticket->user_id,
                'type' => 'reply',
                'actor_id' => $admin->user_id,
                'actor_username' => $admin->username,
                'post_id' => $ticket->ticket_id,
                'comment_id' => null,
                'comment_content' => "Ticket #{$ticket->ticket_id} đã chuyển sang trạng thái: " . ($statusLabels[$request->status] ?? $request->status),
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'ticket' => $ticket
        ]);
    }

    // Admin endpoint - delete ticket
    public function destroy($ticketId)
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $admin = Auth::user();
        
        // Notify user if they have an account
        if ($ticket->user_id) {
            Notification::create([
                'user_id' => $ticket->user_id,
                'type' => 'reply',
                'actor_id' => $admin->user_id,
                'actor_username' => $admin->username,
                'post_id' => $ticket->ticket_id,
                'comment_id' => null,
                'comment_content' => "Ticket #{$ticket->ticket_id} đã bị xóa bởi admin.",
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully'
        ]);
    }

    // Admin endpoint - get statistics
    public function statistics()
    {
        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'high_priority' => SupportTicket::whereIn('priority', ['high', 'urgent'])->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    // User endpoint - get their own tickets
    public function myTickets()
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $tickets = SupportTicket::where('user_id', Auth::id())
            ->with(['admin'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }
}