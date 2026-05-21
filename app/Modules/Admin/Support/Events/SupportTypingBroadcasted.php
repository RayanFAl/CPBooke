<?php

namespace App\Modules\Admin\Support\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTypingBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $typing
     */
    public function __construct(
        public readonly int $ticketId,
        public readonly array $typing,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('support.ticket.'.$this->ticketId)];
    }

    public function broadcastAs(): string
    {
        return 'support.typing.broadcasted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'typing' => $this->typing,
        ];
    }
}