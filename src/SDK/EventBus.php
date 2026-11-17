<?php
declare(strict_types=1);

namespace Nutronyx\SDK;

class EventBus
{
    private static ?EventBus $instance = null;
    /** @var array<string, array<callable>> */
    private array $subscribers = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function subscribe(string $eventName, callable $listener): void
    {
        $this->subscribers[$eventName][] = $listener;
    }

    public function publish(string $eventName, array $payload = []): void
    {
        if (empty($this->subscribers[$eventName])) {
            return;
        }

        foreach ($this->subscribers[$eventName] as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                error_log("Error in event listener for '{$eventName}': " . $e->getMessage());
            }
        }
    }
}
