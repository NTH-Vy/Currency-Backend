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
     * Lấy thời gian còn lại bị cấm (dạng human readable)
     */
    public function getBanRemainingAttribute()
    {
        if (!$this->banned_until || !$this->banned_until->isFuture()) {
            return null;
        }
        return now()->diffForHumans($this->banned_until, ['parts' => 2]);
    }
}