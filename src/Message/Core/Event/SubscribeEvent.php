<?php

namespace Module\Message\Core\Event;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class SubscribeEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public int $creatorId;
    public int $subscriberId;

    public function __construct(int $creatorId, int $subscriberId)
    {
        $this->creatorId = $creatorId;
        $this->subscriberId = $subscriberId;
    }
}
