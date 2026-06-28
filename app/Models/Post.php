<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $primaryKey = 'post_id';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    protected $fillable = ['user_id', 'title', 'content', 'tags', 'is_hot'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function likes() {
        return $this->hasMany(PostLike::class, 'post_id', 'post_id');
    }

    public function comments() {
        return $this->hasMany(Comment::class, 'post_id', 'post_id');
    }
}