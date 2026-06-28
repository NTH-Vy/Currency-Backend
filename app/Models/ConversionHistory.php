<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionHistory extends Model
{
    protected $table = 'conversionhistory';
    
    // Vì khóa chính của bạn tên là history_id (không phải id)
    protected $primaryKey = 'history_id';

    protected $fillable = [
        'user_id', 
        'from_currency', 
        'to_currency', 
        'amount_input', 
        'amount_output'
    ];
    
    // Bảng của bạn có created_at nhưng có lẽ không có updated_at
    // Nếu bị lỗi "Column not found: updated_at", hãy để dòng dưới là false
    public $timestamps = false; 
}