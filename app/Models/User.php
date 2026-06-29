<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'google_id',
        'facebook_id',
        'avatar_url',
        'role',
        'preferred_currency',
        'is_active',
        'banned_until',
        'ban_reason',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'banned_until' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Lấy URL avatar
     */
    public function getAvatarUrlAttribute()
    {
        // Nếu có avatar_url lưu trong DB
        if (isset($this->attributes['avatar_url']) && $this->attributes['avatar_url']) {
            // Nếu là URL đầy đủ (http:// hoặc https://)
            if (filter_var($this->attributes['avatar_url'], FILTER_VALIDATE_URL)) {
                return $this->attributes['avatar_url'];
            }
            // Nếu là đường dẫn local
            return asset('storage/' . $this->attributes['avatar_url']);
        }

        // Nếu có facebook_id -> lấy avatar Facebook
        if ($this->facebook_id) {
            return "https://graph.facebook.com/{$this->facebook_id}/picture?type=large";
        }

        // Nếu có google_id -> lấy avatar Google
        if ($this->google_id) {
            return "https://lh3.googleusercontent.com/a/{$this->google_id}=s96-c";
        }

        return null;
    }

    /**
     * Lấy tên hiển thị
     */
    public function getDisplayNameAttribute()
    {
        return $this->username ?? $this->email;
    }

    /**
     * Kiểm tra user có bị cấm comment không
     */
    public function isBannedFromCommenting(): bool
    {
        if (!$this->banned_until) {
            return false;
        }
        return $this->banned_until->isFuture();
    }

    /**
     * Lấy thời gian còn lại bị cấm
     */
    public function getBanRemainingAttribute()
    {
        if (!$this->banned_until || !$this->banned_until->isFuture()) {
            return null;
        }
        return now()->diffForHumans($this->banned_until, ['parts' => 2]);
    }

    /**
     * Convert to array
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['avatar_url'] = $this->avatar_url;
        $array['display_name'] = $this->display_name;
        return $array;
    }
}