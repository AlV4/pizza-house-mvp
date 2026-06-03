<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Base class for aggregate roots.
 *
 * Aggregates record domain events as a side effect of business operations
 * and expose them via pullDomainEvents() so the Application layer can
 * dispatch them after the transaction commits.
 *
 * Aggregates MUST:
 *   - Validate input in static factory methods (create / reconstitute)
 *   - Keep constructors private or protected
 *   - Expose behavior, never setters
 */
abstract class AggregateRoot
{
    /** @var DomainEvent[] */
    private array $recordedEvents = [];

    final protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Returns and clears all events recorded since the last pull.
     *
     * @return DomainEvent[]
     */
    final public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
