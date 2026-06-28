<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model {
    protected $table = 'favorites';
    protected $primaryKey = 'favorite_id';
    public $timestamps = false;
    protected $fillable = ['user_id', 'news_id'];

    public function news() {
        return $this->belongsTo(News::class, 'news_id', 'news_id');
    }
}