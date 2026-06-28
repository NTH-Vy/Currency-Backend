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
                'comment_content' => "New support ticket from {$request->name}: " . ($request->subject ?: 'Infrastructure Request'),
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

    // Admin endpoint - get all tickets
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'admin']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }

    // Admin endpoint - get single ticket
    public function show($ticketId)
    {
        $ticket = SupportTicket::with(['user', 'admin'])->findOrFail($ticketId);

        return response()->json([
            'success' => true,
            'ticket' => $ticket
        ]);
    }

    // Admin endpoint - respond to ticket
    public function respond(Request $request, $ticketId)
    {
        $request->validate([
            'response' => 'required|string',
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($ticketId);

        $ticket->update([
            'admin_response' => $request->response,
            'status' => $request->status,
            'admin_id' => Auth::id(),
            'responded_at' => now(),
        ]);

        // Create notification for the user if they have an account
        if ($ticket->user_id) {
            Notification::create([
                'user_id' => $ticket->user_id,
                'type' => 'reply',
                'actor_id' => Auth::id(),
                'actor_username' => Auth::user()->username,
                'post_id' => null,
                'comment_id' => null,
                'comment_content' => 'Admin has responded to your support ticket #' . $ticket->ticket_id,
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Response submitted successfully',
            'ticket' => $ticket->load(['user', 'admin'])
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

        $ticket->update([
            'status' => $request->status,
            'priority' => $request->priority ?? $ticket->priority,
        ]);

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
