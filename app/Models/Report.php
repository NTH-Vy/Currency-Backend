<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $primaryKey = 'report_id';
    
    protected $fillable = [
        'reporter_id',
        'comment_id',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'action_taken',
        'ban_duration_days',
        'ban_until',
        'admin_note'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'ban_until' => 'datetime',
        'ban_duration_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants cho các lựa chọn
    const REASONS = [
        'spam' => 'Spam / Quảng cáo',
        'offensive' => 'Nội dung xúc phạm',
        'harassment' => 'Quấy rối',
        'misinformation' => 'Thông tin sai lệch',
        'hate_speech' => 'Kích động thù địch',
        'inappropriate_content' => 'Nội dung không phù hợp',
        'other' => 'Khác'
    ];

    const STATUSES = [
        'pending' => 'Chờ xử lý',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối'
    ];

    const ACTIONS = [
        'none' => 'Không xử lý',
        'warning' => 'Cảnh cáo',
        'delete_comment' => 'Xóa bình luận',
        'temporary_ban' => 'Cấm comment tạm thời',
        'permanent_ban' => 'Cấm comment vĩnh viễn'
    ];

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id', 'user_id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Accessor cho label
    public function getReasonLabelAttribute()
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getActionLabelAttribute()
    {
        return self::ACTIONS[$this->action_taken] ?? $this->action_taken;
    }
}