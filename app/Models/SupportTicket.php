<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $table = 'support_tickets';
    protected $primaryKey = 'ticket_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'priority',
        'admin_response',
        'admin_id',
        'responded_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    // Relationship with user who created the ticket
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Relationship with admin who responded
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id', 'user_id');
    }

    // Scope for open tickets
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    // Scope for in-progress tickets
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    // Scope for resolved tickets
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    // Scope for closed tickets
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // Scope for high priority tickets
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }
}
