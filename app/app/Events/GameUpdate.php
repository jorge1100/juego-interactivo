<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GameUpdate implements ShouldBroadcast
{
    public string $type;

    /** @var array<string,mixed> */
    public array $payload;

    /**
     * @param  array<string,mixed>  $payload
     */
    public function __construct(string $type, array $payload = [])
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [new Channel('game-channel')];
    }

    public function broadcastAs(): string
    {
        return 'GameUpdate';
    }
}