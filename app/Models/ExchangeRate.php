<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'exchangerates';
    protected $primaryKey = 'rate_id';
    public $timestamps = false;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'exchange_rate',
        'volume_24h',
        'volatility',
        'price_change_percent',
        'trend',
        'last_updated',
        'source',
        'bid_price',
        'ask_price',
        'change_24h',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'volume_24h' => 'decimal:2',
        'price_change_percent' => 'decimal:4',
        'last_updated' => 'datetime',
    ];

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'currency_code');
    }

    public function targetCurrency()
    {
        return $this->belongsTo(Currency::class, 'target_currency', 'currency_code');
    }

    public function history()
    {
        return $this->hasMany(ExchangeRateHistory::class, 'base_currency', 'base_currency')
            ->where('target_currency', $this->target_currency);
    }
}
