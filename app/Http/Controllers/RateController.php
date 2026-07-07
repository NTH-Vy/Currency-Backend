<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ExchangeRate;

class RateController extends Controller
{
    public function quote(Request $request)
    {
        $request->validate([
            'amount' => 'nullable|numeric',
            'from' => 'required|string|max:10',
            'to' => 'required|string|max:10',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $amount = $request->input('amount', 1);

        $exchangeRate = ExchangeRate::where('base_currency', $from)
            ->where('target_currency', $to)
            ->first();

        if ($exchangeRate) {
            $rate = $exchangeRate->exchange_rate;
            $result = $amount * $rate;

            return response()->json([
                'success' => true,
                'result' => $result,
                'rate' => $rate,
                'source' => $exchangeRate->source,
            ]);
        }

        $apiKey = config('services.exchange_rate.api_key');
        $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$from}/{$to}/{$amount}");

        if ($response->successful()) {
            $data = $response->json();
            $result = $data['conversion_result'];

            return response()->json([
                'success' => true,
                'result' => $result,
                'rate' => $data['conversion_rate'],
                'source' => 'external_api',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'API Error'], 500);
    }
}
