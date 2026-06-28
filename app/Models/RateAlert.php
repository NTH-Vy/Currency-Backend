<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RateAlert extends Model
{
    use HasFactory;

    protected $table = 'rate_alerts';
    protected $primaryKey = 'alert_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'base_currency',
        'target_currency',
        'target_rate',
        'condition',
        'is_active',
        'is_triggered',
        'triggered_at',
        'created_at',
    ];

    protected $casts = [
        'target_rate' => 'decimal:6',
        'is_active' => 'boolean',
        'is_triggered' => 'boolean',
        'triggered_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'currency_code');
    }

    public function targetCurrency()
    {
        return $this->belongsTo(Currency::class, 'target_currency', 'currency_code');
    }
}
