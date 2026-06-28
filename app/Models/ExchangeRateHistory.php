<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateHistory extends Model
{
    use HasFactory;

    protected $table = 'exchangeratehistory';
    protected $primaryKey = 'history_id';
    public $timestamps = false;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate_value',
        'source',
        'recorded_at',
    ];

    protected $casts = [
        'rate_value' => 'decimal:6',
        'recorded_at' => 'datetime',
    ];

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'currency_code');
    }

    public function targetCurrency()
    {
        return $this->belongsTo(Currency::class, 'target_currency', 'currency_code');
    }
}
