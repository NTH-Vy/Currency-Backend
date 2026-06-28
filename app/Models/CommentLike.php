<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentLike extends Model
{
    protected $table = 'comment_likes';
    protected $primaryKey = 'like_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'comment_id'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_id');
    }
}
