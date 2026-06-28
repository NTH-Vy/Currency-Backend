<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';
    
    protected $fillable = [
        'key',
        'value'
    ];

    public $timestamps = true;

    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value)
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function isMaintenanceMode()
    {
        $value = static::get('maintenanceMode', false);
        return in_array($value, [true, 1, '1', 'true'], true);
    }
}