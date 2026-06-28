<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $pair;
    public $price;
    public $change;
    public $trend;
    public $volatility;
    public $volume;
    public $timestamp;

    public function __construct($pair, $price, $change, $trend, $volatility = 'Medium', $volume = '0')
    {
        $this->pair = $pair;
        $this->price = $price;
        $this->change = $change;
        $this->trend = $trend;
        $this->volatility = $volatility;
        $this->volume = $volume;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn()
    {
        return new Channel('rates');
    }

    public function broadcastAs()
    {
        return 'price_update';
    }
}