<?php
// app/Events/CurrencyStrengthUpdated.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CurrencyStrengthUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $base;
    public $data;
    public $timestamp;

    public function __construct($base, $data)
    {
        $this->base = $base;
        $this->data = $data;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn()
    {
        return new Channel("currency-strength.{$this->base}");
    }

    public function broadcastAs()
    {
        return 'currency-strength.updated';
    }
}
