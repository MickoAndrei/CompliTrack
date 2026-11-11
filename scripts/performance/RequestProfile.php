<?php

namespace CompliTrack\Diagnostics;

use MongoDB\Driver\Monitoring\{CommandFailedEvent, CommandStartedEvent, CommandSubscriber, CommandSucceededEvent};

// Loaded only by the opt-in diagnostic development router, never by production routes.
class RequestProfile implements CommandSubscriber
{
    public array $counts = [];
    public array $durations = [];
    public ?float $connectionCreatedAt = null;
    public ?float $firstCommandAt = null;
    public float $actionMs = 0;
    public array $sessionMs = [];
    private array $pending = [];

    public function commandStarted(CommandStartedEvent $event): void
    {
        $this->firstCommandAt ??= microtime(true);
        $name = $event->getCommandName();
        $collection = $event->getCommand()->$name ?? '';
        $key = $name.':'.(is_string($collection) ? $collection : 'database');
        $this->pending[$event->getRequestId()] = $key;
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $key = $this->pending[$event->getRequestId()] ?? 'unknown';
        $this->durations[$key] = ($this->durations[$key] ?? 0) + $event->getDurationMicros() / 1000;
        unset($this->pending[$event->getRequestId()]);
    }

    public function commandFailed(CommandFailedEvent $event): void
    {
        unset($this->pending[$event->getRequestId()]);
    }
}

class TimedSessionHandler implements \SessionHandlerInterface, \Illuminate\Session\ExistenceAwareInterface
{
    public function __construct(private \SessionHandlerInterface $handler, private RequestProfile $profile) {}
    private function measure(string $name, callable $callback): mixed
    {
        $start = microtime(true);
        try { return $callback(); }
        finally { $this->profile->sessionMs[$name] = ($this->profile->sessionMs[$name] ?? 0) + (microtime(true) - $start) * 1000; }
    }
    public function open(string $path, string $name): bool { return $this->handler->open($path, $name); }
    public function close(): bool { return $this->handler->close(); }
    public function read(string $id): string|false { return $this->measure('read', fn () => $this->handler->read($id)); }
    public function write(string $id, string $data): bool { return $this->measure('write', fn () => $this->handler->write($id, $data)); }
    public function destroy(string $id): bool { return $this->handler->destroy($id); }
    public function gc(int $max_lifetime): int|false { return $this->handler->gc($max_lifetime); }
    public function setExists($value) { if ($this->handler instanceof \Illuminate\Session\ExistenceAwareInterface) { $this->handler->setExists($value); } return $this; }
}

class ActionTimer
{
    public function handle($request, $next)
    {
        $start = microtime(true);
        try { return $next($request); }
        finally { $request->attributes->get('performance.profile')->actionMs += (microtime(true) - $start) * 1000; }
    }
}
