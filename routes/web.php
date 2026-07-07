<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// ===== ROUTE TẠM ĐỂ KIỂM TRA - XÓA SAU KHI TEST XONG =====

// Kiểm tra biến môi trường
Route::get('/debug-env', function () {
    return response()->json([
        'frontend_url' => config('app.frontend_url'),
        'app_env' => config('app.env'),
        'app_url' => config('app.url'),
    ]);
});

// Chạy fetch rates 1 lần và xem kết quả
Route::get('/debug-fetch-rates', function () {
    $startTime = microtime(true);
    
    Artisan::call('rates:fetch-realtime', ['--once' => true]);
    
    $output = Artisan::output();
    $executionTime = round(microtime(true) - $startTime, 2);
    
    return response()->json([
        'status' => 'completed',
        'execution_time' => $executionTime . 's',
        'output' => explode("\n", trim($output)),
    ]);
});

// ===== HẾT ROUTE TẠM =====
