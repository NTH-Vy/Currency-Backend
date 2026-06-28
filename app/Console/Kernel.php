<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $interval = env('RATE_FETCH_INTERVAL', '5');

        $job = $schedule->command('rates:fetch')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/rates-fetch.log'));

        if (is_numeric($interval)) {
            $minutes = (int) $interval;

            if ($minutes === 1) {
                $job->everyMinute();
            } elseif ($minutes === 5) {
                $job->everyFiveMinutes();
            } elseif ($minutes === 15) {
                $job->everyFifteenMinutes();
            } elseif ($minutes === 30) {
                $job->everyThirtyMinutes();
            } elseif ($minutes === 60) {
                $job->hourly();
            } else {
                $job->cron("*/{$minutes} * * * *");
            }
        } elseif ($interval === 'hourly') {
            $job->hourly();
        } elseif ($interval === 'daily') {
            $job->daily();
        } else {
            $job->everyFiveMinutes();
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
