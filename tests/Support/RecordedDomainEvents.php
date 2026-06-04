<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\DomainEvent;

/**
 * Test-only, shared collector of every domain event published on the event bus
 * during a test. Registered as a service in the test container (see the
 * when@test block in config/services.yaml) and populated by
 * {@see RecordingEventSubscriber}.
 *
 * Integration tests fetch this from the container to assert which events a flow
 * published, satisfying acceptance criteria phrased as "… is published".
 */
final class RecordedDomainEvents
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    public function clear(): void
    {
        $this->events = [];
    }

    /**
     * @return list<DomainEvent>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @template T of DomainEvent
     *
     * @param class-string<T> $type
     *
     * @return list<T>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (DomainEvent $event): bool => $event instanceof $type,
        ));
    }
}
