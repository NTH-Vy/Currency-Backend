<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyFavorite extends Model
{
    use HasFactory;

    protected $table = 'currency_favorites';
    protected $primaryKey = 'favorite_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'base_currency',
        'target_currency',
        'created_at',
    ];

    protected $casts = [
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
