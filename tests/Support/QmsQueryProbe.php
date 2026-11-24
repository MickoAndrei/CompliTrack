<?php
namespace Tests\Support;

use MongoDB\Driver\Monitoring\{CommandSubscriber, CommandStartedEvent, CommandSucceededEvent, CommandFailedEvent};

final class QmsQueryProbe implements CommandSubscriber
{
    public int $count = 0;
    public float $milliseconds = 0;
    public function commandStarted(CommandStartedEvent $event): void { $this->count++; }
    public function commandSucceeded(CommandSucceededEvent $event): void { $this->milliseconds += $event->getDurationMicros() / 1000; }
    public function commandFailed(CommandFailedEvent $event): void {}
}
