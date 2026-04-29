<?php

namespace Idei\Usim\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsimEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $eventName,
        public array $params = []
    ) {
    }
}
