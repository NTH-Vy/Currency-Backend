<?php
// app/Console/Commands/FetchRealtimeRates.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Events\RateUpdated;
use App\Events\MarketPulseUpdated;
use App\Events\CurrencyStrengthUpdated;

class FetchRealtimeRates extends Command
{
    protected $signature = 'rates:fetch-realtime {--pair=} {--once}';
    protected $description = 'Fetch real-time exchange rates from external API and broadcast via WebSocket';

    private $apiKey;
    private $pairs = [
        ['USD', 'VND'],
        ['EUR', 'VND'],
        ['USD', 'EUR'],
        ['GBP', 'USD'],
        ['USD', 'JPY'],
        ['EUR', 'USD'],
        ['AUD', 'USD'],
        ['USD', 'CAD'],
        ['USD', 'CHF'],
        ['GBP', 'EUR'],
        ['JPY', 'USD'],
        ['CNY', 'USD'],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = config('services.exchange_rate.api_key');
    }

    public function handle()
    {
        $specificPair = $this->option('pair');
        $once = $this->option('once');
        
        if ($specificPair) {
            $parts = explode('/', $specificPair);
            if (count($parts) == 2) {
                $this->fetchAndBroadcast($parts[0], $parts[1]);
            } else {
                $this->error('Invalid pair format. Use FROM/TO (e.g., USD/VND)');
            }
            return Command::SUCCESS;
        }
        
        do {
            $startTime = microtime(true);
            
            foreach ($this->pairs as $pair) {
                $this->fetchAndBroadcast($pair[0], $pair[1]);
                // Respect rate limit (free tier: ~100 requests/minute)
                usleep(500000); // 0.5 second delay
            }
            
            $executionTime = microtime(true) - $startTime;
            $this->info("Cycle completed in " . round($executionTime, 2) . " seconds");
            
            if ($once) {
                break;
            }
            
            // Wait 30 seconds before next cycle
            sleep(30);
            
        } while (!$once);
        
        return Command::SUCCESS;
    }
    
    private function fetchAndBroadcast($from, $to)
    {
        try {
            // Fetch from API
            $response = Http::timeout(10)->get(
                "https://v6.exchangerate-api.com/v6/{$this->apiKey}/pair/{$from}/{$to}"
            );
            
            if (!$response->successful()) {
                $this->warn("Failed to fetch {$from}/{$to}: HTTP " . $response->status());
                return;
            }
            
            $data = $response->json();
            
            if (!isset($data['conversion_rate']) || $data['result'] !== 'success') {
                $this->warn("Invalid response for {$from}/{$to}");
                return;
            }
            
            $newRate = (float) $data['conversion_rate'];

            // Get rate from 24 hours ago from history table for true 24h change
            $rate24hAgo = ExchangeRateHistory::where('base_currency', $from)
                ->where('target_currency', $to)
                ->where('recorded_at', '>=', now()->subHours(25))
                ->where('recorded_at', '<=', now()->subHours(23))
                ->orderBy('recorded_at', 'asc')
                ->first();

            // Calculate true 24h change percentage
            $change24hPercent = 0;
            if ($rate24hAgo && $rate24hAgo->rate_value > 0) {
                $change24hPercent = (($newRate - $rate24hAgo->rate_value) / $rate24hAgo->rate_value) * 100;
            } else {
                // Fallback: use current database rate if no 24h history
                $currentRate = ExchangeRate::where('base_currency', $from)
                    ->where('target_currency', $to)
                    ->first();
                if ($currentRate && $currentRate->exchange_rate > 0) {
                    $change24hPercent = (($newRate - $currentRate->exchange_rate) / $currentRate->exchange_rate) * 100;
                }
            }
            
            // Update or create exchange rate
            $exchangeRate = ExchangeRate::updateOrCreate(
                ['base_currency' => $from, 'target_currency' => $to],
                [
                    'exchange_rate' => $newRate,
                    'source' => 'external_api',
                    'last_updated' => now(),
                    'price_change_percent' => $change24hPercent,
                    'change_24h' => $change24hPercent,
                    'trend' => $change24hPercent > 0 ? 'up' : ($change24hPercent < 0 ? 'down' : 'neutral'),
                    'volatility' => $this->calculateVolatility($from, $to, $newRate),
                    'volume_24h' => $this->estimateVolume($from, $to),
                    'bid_price' => $newRate * 0.9995,
                    'ask_price' => $newRate * 1.0005,
                ]
            );
            
            // Save to history
            ExchangeRateHistory::create([
                'base_currency' => $from,
                'target_currency' => $to,
                'rate_value' => $newRate,
                'source' => 'external_api',
                'recorded_at' => now(),
            ]);
            
            // Clean old history (keep last 7 days)
            ExchangeRateHistory::where('base_currency', $from)
                ->where('target_currency', $to)
                ->where('recorded_at', '<', now()->subDays(7))
                ->delete();
            
            // Broadcast WebSocket events (skip on production if Reverb is not configured)
            $reverbHost = config('reverb.servers.reverb.hostname', 'localhost');
            $isReverbAvailable = $reverbHost !== 'localhost' || config('app.env') !== 'production';

            if ($isReverbAvailable) {
                try {
                    broadcast(new RateUpdated($from, $to, $newRate, $change24hPercent));

                    $marketPulseData = [
                        'volatility' => $exchangeRate->volatility ?? 'Medium',
                        'priceChangePercent' => $change24hPercent,
                        'dayLow' => $newRate * 0.995,
                        'dayHigh' => $newRate * 1.005,
                        'sentimentBuy' => 65,
                        'sentimentSell' => 35,
                        'liquidity' => 'Deep',
                        'volume24h' => $exchangeRate->volume_24h ?? $this->estimateVolume($from, $to),
                    ];
                    broadcast(new MarketPulseUpdated($from, $to, $marketPulseData));

                    $strengthData = $this->calculateCurrencyStrength($from);
                    broadcast(new CurrencyStrengthUpdated($from, $strengthData));

                    $this->info("✓ {$from}/{$to}: {$newRate} (" . ($change24hPercent > 0 ? '+' : '') . round($change24hPercent, 4) . "%) - Broadcasted");
                } catch (\Exception $e) {
                    $this->info("✓ {$from}/{$to}: {$newRate} (" . ($change24hPercent > 0 ? '+' : '') . round($change24hPercent, 4) . "%) - Saved (broadcast skipped)");
                }
            } else {
                $this->info("✓ {$from}/{$to}: {$newRate} (" . ($change24hPercent > 0 ? '+' : '') . round($change24hPercent, 4) . "%) - Saved to DB");
            }
            
        } catch (\Exception $e) {
            $this->error("Error fetching {$from}/{$to}: " . $e->getMessage());
        }
    }
    
    private function calculateVolatility($from, $to, $currentRate)
    {
        // Get recent history to calculate volatility
        $recentRates = ExchangeRateHistory::where('base_currency', $from)
            ->where('target_currency', $to)
            ->where('recorded_at', '>', now()->subHours(24))
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get();
        
        if ($recentRates->count() < 5) {
            return 'Low';
        }
        
        $rates = $recentRates->pluck('rate_value')->toArray();
        $avg = array_sum($rates) / count($rates);
        $variance = array_sum(array_map(function($r) use ($avg) {
            return pow($r - $avg, 2);
        }, $rates)) / count($rates);
        $stdDev = sqrt($variance);
        $volatilityPercent = ($stdDev / $avg) * 100;
        
        if ($volatilityPercent > 1.5) return 'High';
        if ($volatilityPercent > 0.5) return 'Medium';
        return 'Low';
    }
    
    private function estimateVolume($from, $to)
    {
        // Estimate volume based on currency pair
        $baseVolumes = [
            'USD' => 5000000000,
            'EUR' => 3500000000,
            'GBP' => 2000000000,
            'JPY' => 1500000000,
            'AUD' => 500000000,
            'CAD' => 400000000,
            'CHF' => 300000000,
            'CNY' => 200000000,
            'VND' => 100000000,
        ];
        
        $volume = ($baseVolumes[$from] ?? 100000000) * 0.5;
        return $volume;
    }
    
    private function calculateCurrencyStrength($base)
    {
        $targets = ['EUR', 'JPY', 'GBP', 'VND', 'AUD', 'CAD', 'CHF', 'CNY'];
        $targets = array_filter($targets, function($c) use ($base) {
            return $c !== $base;
        });
        $targets = array_slice($targets, 0, 6);
        
        $targetData = [];
        foreach ($targets as $currency) {
            $rate = ExchangeRate::where('base_currency', $base)
                ->where('target_currency', $currency)
                ->first();
            
            $change24h = $rate ? (float)($rate->price_change_percent ?? 0) : (float)(rand(-100, 100) / 100);
            $strength = (float)($rate ? rand(50, 100) : rand(30, 70));
            
            $targetData[] = [
                'currency' => $currency,
                'change24h' => round($change24h, 2),
                'strength' => $strength,
            ];
        }
        
        return [
            'base' => $base,
            'targets' => $targetData,
        ];
    }
}