<?php

namespace Tests\Support;

use MongoDB\Driver\Monitoring\{CommandFailedEvent, CommandStartedEvent, CommandSubscriber, CommandSucceededEvent};

class MongoCommandCounter implements CommandSubscriber
{
    public array $counts = [];

    public function commandStarted(CommandStartedEvent $event): void
    {
        $name = $event->getCommandName();
        $collection = $event->getCommand()->$name ?? '';
        if (is_string($collection)) {
            $key = $name.':'.$collection;
            $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
        }
    }

    public function commandSucceeded(CommandSucceededEvent $event): void {}

    public function commandFailed(CommandFailedEvent $event): void {}
}
