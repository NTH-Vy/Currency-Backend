<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'news';
    protected $primaryKey = 'news_id';
    public $timestamps = false; // database của bạn dùng published_at thay vì created_at/updated_at

    protected $fillable = ['title', 'content', 'image_url', 'author_id', 'category', 'published_at'];

    // Liên kết với User (Tác giả)
    public function author() {
        return $this->belongsTo(User::class, 'author_id', 'user_id');
    }

    // Liên kết với Bình luận
    public function comments() {
        return $this->hasMany(Comment::class, 'news_id', 'news_id');
    }
}