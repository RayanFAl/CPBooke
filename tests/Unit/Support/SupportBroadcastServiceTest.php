<?php

namespace Tests\Unit\Support;

use App\Modules\Admin\Support\Events\SupportTicketUpdatedBroadcasted;
use App\Modules\Support\Services\SupportBroadcastService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\TestCase;

class SupportBroadcastServiceTest extends TestCase
{
    public function test_dispatch_swallows_broadcast_transport_failures(): void
    {
        $event = new SupportTicketUpdatedBroadcasted(1, ['id' => 1]);

        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->andReturnUsing(function (object $dispatchedEvent) use ($event): ?array {
                if ($dispatchedEvent === $event) {
                    throw new BroadcastException('Pusher error: cURL error 7: Failed to connect.');
                }

                return null;
            });

        $this->instance('events', $dispatcher);

        app(SupportBroadcastService::class)->dispatch($event);

        $this->assertTrue(true);
    }
}
