<?php
// app/Events/MarketPulseUpdated.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketPulseUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $from;
    public $to;
    public $data;
    public $timestamp;

    public function __construct($from, $to, $data)
    {
        $this->from = $from;
        $this->to = $to;
        $this->data = $data;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn()
    {
        return new Channel("market-pulse.{$this->from}.{$this->to}");
    }

    public function broadcastAs()
    {
        return 'market-pulse.updated';
    }
}
