<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $table = 'comments';
    protected $primaryKey = 'comment_id';
    public $timestamps = false;

    protected $fillable = ['user_id', 'news_id', 'post_id', 'content', 'rating', 'parent_comment_id'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function post() {
        return $this->belongsTo(Post::class, 'post_id', 'post_id');
    }

    public function news() {
        return $this->belongsTo(News::class, 'news_id', 'news_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_comment_id', 'comment_id');
    }

    public function parentComment()
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id', 'comment_id');
    }

    // Quan hệ với reports
    public function reports()
    {
        return $this->hasMany(Report::class, 'comment_id', 'comment_id');
    }

    // Quan hệ với likes
    public function likes()
    {
        return $this->hasMany(CommentLike::class, 'comment_id', 'comment_id');
    }

    // Scope để lấy comment news
    public function scopeNewsComments($query)
    {
        return $query->whereNotNull('news_id');
    }

    // Scope để lấy comment của post
    public function scopePostComments($query)
    {
        return $query->whereNotNull('post_id');
    }

    // Đếm số báo cáo pending
    public function getReportCountAttribute()
    {
        return $this->reports()->where('status', 'pending')->count();
    }

    // Kiểm tra có báo cáo pending không
    public function getIsReportedAttribute()
    {
        return $this->reports()->where('status', 'pending')->exists();
    }

    // Đếm số likes
    public function getLikesCountAttribute()
    {
        return $this->likes()->count();
    }
}