<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use Carbon\Carbon;

class FetchExchangeRates extends Command
{
    protected $signature = 'rates:fetch';
    protected $description = 'Fetch exchange rates from multiple APIs (CoinGecko, Binance, Fixer)';

    public function handle()
    {
        $this->info('Starting to fetch exchange rates...');

        // Fetch fiat rates from Fixer.io
        $this->fetchFiatRates();

        // Fetch crypto rates from CoinGecko
        $this->fetchCryptoRates();

        // Fetch crypto rates from Binance
        $this->fetchBinanceRates();

        $this->info('Exchange rates updated successfully!');
        return Command::SUCCESS;
    }

    private function fetchFiatRates()
    {
        $this->info('Fetching fiat rates from exchangerate.host...');

        try {
            $fiatCurrencies = Currency::where('type', 'fiat')
                ->where('is_active', 1)
                ->get();

            if ($fiatCurrencies->isEmpty()) {
                $this->warn('No fiat currencies found in database');
                return;
            }

            $symbols = $fiatCurrencies
                ->pluck('currency_code')
                ->map(fn ($code) => strtoupper($code))
                ->filter(fn ($code) => $code !== 'USD')
                ->implode(',');

            if (empty($symbols)) {
                $this->warn('No fiat currencies to fetch besides USD');
                return;
            }

            $response = Http::get('https://api.exchangerate.host/latest', [
                'base' => 'USD',
                'symbols' => $symbols,
            ]);

            if (! $response->successful()) {
                $this->error('Failed to fetch fiat rates from exchangerate.host');
                return;
            }

            $data = $response->json();
            $timestamp = Carbon::now();

            if (! isset($data['rates']) || ! is_array($data['rates'])) {
                $this->error('Invalid response from exchangerate.host');
                return;
            }

            foreach ($fiatCurrencies as $currency) {
                $currencyCode = strtoupper($currency->currency_code);

                if ($currencyCode === 'USD') {
                    continue;
                }

                $rate = $data['rates'][$currencyCode] ?? null;
                if (! is_numeric($rate) || $rate <= 0) {
                    continue;
                }

                $this->updateOrCreateRate('USD', $currencyCode, (float) $rate, 'exchangerate.host', $timestamp);
                $this->updateOrCreateRate($currencyCode, 'USD', 1 / (float) $rate, 'exchangerate.host', $timestamp);
            }

            $this->info('Fiat rates updated successfully');
        } catch (\Exception $e) {
            $this->error('Error fetching fiat rates: ' . $e->getMessage());
        }
    }

    private function fetchCryptoRates()
    {
        $this->info('Fetching crypto rates from CoinGecko...');

        try {
            // Get all crypto currencies from database
            $cryptoCurrencies = Currency::where('type', 'crypto')
                ->where('api_source', 'coingecko')
                ->where('is_active', 1)
                ->get();

            if ($cryptoCurrencies->isEmpty()) {
                $this->warn('No crypto currencies found in database');
                return;
            }

            // Build API symbols list
            $symbols = $cryptoCurrencies->pluck('api_symbol')->implode(',');

            $response = Http::get("https://api.coingecko.com/api/v3/coins/markets", [
                'vs_currency' => 'usd',
                'ids' => $symbols,
                'order' => 'market_cap_desc',
                'per_page' => 50,
                'page' => 1,
                'sparkline' => false,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $timestamp = Carbon::now();

                foreach ($data as $coin) {
                    $currency = $cryptoCurrencies->firstWhere('api_symbol', $coin['id']);

                    if ($currency) {
                        $rate = $coin['current_price'];
                        $change24h = $coin['price_change_percentage_24h'] ?? null;

                        // Update USD to crypto rate
                        $this->updateOrCreateRate(
                            'USD',
                            $currency->currency_code,
                            $rate,
                            'coingecko',
                            $timestamp,
                            null,
                            null,
                            $change24h
                        );

                        // Also create inverse rate (crypto to USD)
                        $this->updateOrCreateRate(
                            $currency->currency_code,
                            'USD',
                            1 / $rate,
                            'coingecko',
                            $timestamp,
                            null,
                            null,
                            -$change24h
                        );
                    }
                }

                $this->info('Crypto rates from CoinGecko updated successfully');
            } else {
                $this->error('Failed to fetch from CoinGecko API');
            }
        } catch (\Exception $e) {
            $this->error('Error fetching crypto rates from CoinGecko: ' . $e->getMessage());
        }
    }

    private function fetchBinanceRates()
    {
        $this->info('Fetching crypto rates from Binance...');

        try {
            // Get all crypto currencies from database
            $cryptoCurrencies = Currency::where('type', 'crypto')
                ->where('api_source', 'binance')
                ->where('is_active', 1)
                ->get();

            if ($cryptoCurrencies->isEmpty()) {
                $this->warn('No Binance crypto currencies found in database');
                return;
            }

            $timestamp = Carbon::now();

            foreach ($cryptoCurrencies as $currency) {
                $symbol = strtoupper($currency->api_symbol) . 'USDT';

                $response = Http::get("https://api.binance.com/api/v3/ticker/24hr", [
                    'symbol' => $symbol
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['symbol'])) {
                        $bidPrice = floatval($data['bidPrice']);
                        $askPrice = floatval($data['askPrice']);
                        $change24h = floatval($data['priceChangePercent']);
                        $lastPrice = floatval($data['lastPrice']);

                        // Update USD to crypto rate
                        $this->updateOrCreateRate(
                            'USD',
                            $currency->currency_code,
                            $lastPrice,
                            'binance',
                            $timestamp,
                            $bidPrice,
                            $askPrice,
                            $change24h
                        );
                    }
                }
            }

            $this->info('Crypto rates from Binance updated successfully');
        } catch (\Exception $e) {
            $this->error('Error fetching crypto rates from Binance: ' . $e->getMessage());
        }
    }

    private function updateOrCreateRate(
        string $baseCurrency,
        string $targetCurrency,
        float $rate,
        string $source,
        Carbon $timestamp,
        ?float $bidPrice = null,
        ?float $askPrice = null,
        ?float $change24h = null
    ) {
        // Calculate trend based on change
        $trend = 'neutral';
        $priceChangePercent = $change24h ?? 0;
        if ($change24h !== null) {
            $trend = $change24h > 0 ? 'up' : ($change24h < 0 ? 'down' : 'neutral');
        }

        // Update or create current rate
        $exchangeRate = ExchangeRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'target_currency' => $targetCurrency,
            ],
            [
                'exchange_rate' => $rate,
                'source' => $source,
                'bid_price' => $bidPrice,
                'ask_price' => $askPrice,
                'change_24h' => $change24h,
                'price_change_percent' => $priceChangePercent,
                'trend' => $trend,
                'last_updated' => $timestamp,
            ]
        );

        // Store in history
        ExchangeRateHistory::create([
            'base_currency' => $baseCurrency,
            'target_currency' => $targetCurrency,
            'rate_value' => $rate,
            'source' => $source,
            'recorded_at' => $timestamp,
        ]);

        // Clean old history (keep only last 30 days)
        ExchangeRateHistory::where('recorded_at', '<', Carbon::now()->subDays(30))
            ->delete();

        $this->line("Updated {$baseCurrency}/{$targetCurrency}: {$rate} ({$source})");
    }
}
