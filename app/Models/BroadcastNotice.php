<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BroadcastNotice extends Model
{
    use HasFactory;

    protected $table = 'broadcast_notices';
    protected $primaryKey = 'notice_id';
    public $timestamps = true;

    protected $fillable = [
        'admin_id',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id', 'user_id');
    }
}
