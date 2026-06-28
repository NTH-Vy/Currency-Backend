<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        $defaultConfig = [
            'platformName' => 'CORTEX NETWORK',
            'maintenanceMode' => false,
            'maintenanceMessage' => 'The system is undergoing maintenance. Please check back later.',
            'maintenanceEstimatedEnd' => null,
            'publicRegistration' => true,
            'apiEndpoint' => 'http://127.0.0.1:8000/api/v1',
            'apiKey' => 'CTX-9921-X88-ALPHA-LEDGER-001',
            'syncFrequency' => '30s',
            'authStrict' => true,
            'autoDefuse' => true
        ];

        foreach ($defaultConfig as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return response()->json($settings);
    }

    public function getMaintenanceStatus()
    {
        return response()->json([
            'maintenance' => Setting::isMaintenanceMode(),
            'message' => Setting::get('maintenanceMessage', 'The system is undergoing maintenance. Please check back later.'),
            'estimated_end' => Setting::get('maintenanceEstimatedEnd', null)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'platformName' => 'required|string|max:255',
            'maintenanceMode' => 'required|boolean',
            'maintenanceMessage' => 'nullable|string|max:500',
            'maintenanceEstimatedEnd' => 'nullable|string', // Đổi từ date sang string
            'publicRegistration' => 'required|boolean',
            'apiEndpoint' => 'required|string|max:255',
            'apiKey' => 'required|string|max:255',
            'syncFrequency' => 'required|string|max:50',
            'authStrict' => 'required|boolean',
            'autoDefuse' => 'required|boolean'
        ]);

        // XỬ LÝ maintenanceEstimatedEnd - HỖ TRỢ CẢ 2 LOẠI FORMAT
        if (!empty($validated['maintenanceEstimatedEnd'])) {
            $input = $validated['maintenanceEstimatedEnd'];
            $date = null;
            
            // LOẠI 1: FORMAT 24h - "2026-06-14 18:33:00" hoặc "2026-06-14T18:33"
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $input)) {
                try {
                    $date = Carbon::parse($input);
                } catch (\Exception $e) {
                    $date = null;
                }
            }
            
            // LOẠI 2: FORMAT 12h - "14/06/2026 06:33 PM" hoặc "14/06/2026 06:33:00 PM"
            if (!$date && preg_match('/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}(:\d{2})? (AM|PM)/i', $input)) {
                try {
                    $date = Carbon::createFromFormat('d/m/Y h:i:s A', $input);
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::createFromFormat('d/m/Y h:i A', $input);
                    } catch (\Exception $e2) {
                        $date = null;
                    }
                }
            }
            
            // Nếu parse được, chuẩn hóa về format Y-m-d H:i:s
            if ($date) {
                $validated['maintenanceEstimatedEnd'] = $date->format('Y-m-d H:i:s');
            } else {
                $validated['maintenanceEstimatedEnd'] = null;
            }
        }

        DB::beginTransaction();
        try {
            foreach ($validated as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}