<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $table = 'currencies';
    protected $primaryKey = 'currency_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'currency_code',
        'currency_name',
        'symbol',
        'type',
        'api_source',
        'api_symbol',
        'is_active',
        'min_amount',
        'max_amount',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
    ];

    public function exchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency', 'currency_code');
    }

    public function targetExchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'target_currency', 'currency_code');
    }
}
