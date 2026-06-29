<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    public $timestamps = false;
    protected $fillable = ['user_id', 'type', 'actor_id', 'actor_username', 'post_id', 'comment_id', 'comment_content', 'is_read', 'created_at'];

    const TYPES = [
        'like' => 'Like',
        'reply' => 'Reply',
        'mention' => 'Mention',
        'warning' => 'Warning',
        'ban' => 'Ban',
        'report' => 'Report'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function actor() {
        return $this->belongsTo(User::class, 'actor_id', 'user_id');
    }

    public function post() {
        return $this->belongsTo(Post::class, 'post_id', 'post_id');
    }

    public function comment() {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_id');
    }

    // Scope for unread notifications
    public function scopeUnread($query)
    {
        return $query->where('is_read', 0);
    }

    // Scope for read notifications
    public function scopeRead($query)
    {
        return $query->where('is_read', 1);
    }

    // Get notification type label
    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}