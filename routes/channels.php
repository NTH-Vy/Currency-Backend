<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Public channel for rate updates - no authentication required
Broadcast::channel('rates.{from}.{to}', function ($user, $from, $to) {
    // Allow public access to rate channels
    return true;
});
