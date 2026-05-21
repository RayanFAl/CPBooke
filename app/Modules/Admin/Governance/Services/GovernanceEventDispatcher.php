<?php

namespace App\Modules\Admin\Governance\Services;

use Illuminate\Contracts\Events\Dispatcher;

class GovernanceEventDispatcher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}