<?php

namespace Idei\Usim\Events;

use Idei\Usim\Support\UIStateManager;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsimEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $eventName,
        public array $params = []
    ) {
        $this->params['client_id'] = UIStateManager::getOrCreateClientId();
        $this->params['storage'] = request()->storage ?? [];
    }
}
