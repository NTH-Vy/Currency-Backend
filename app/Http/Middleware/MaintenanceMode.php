<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        $isMaintenance = Setting::isMaintenanceMode();
        
        if (!$isMaintenance) {
            return $next($request);
        }

        $token = $request->bearerToken();
        $isAdmin = false;
        
        if ($token) {
            $personalAccessToken = PersonalAccessToken::findToken($token);
            if ($personalAccessToken && $user = $personalAccessToken->tokenable) {
                if ($user->role === 'admin') {
                    $isAdmin = true;
                }
            }
        }

        if ($isAdmin) {
            return $next($request);
        }

        $maintenanceMessage = Setting::get('maintenanceMessage', 'Hệ thống đang bảo trì. Vui lòng quay lại sau.');
        $maintenanceEstimatedEnd = Setting::get('maintenanceEstimatedEnd', null);

        return response()->json([
            'success' => false,
            'message' => $maintenanceMessage,
            'maintenance' => true,
            'estimated_end' => $maintenanceEstimatedEnd
        ], 503);
    }
}