<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marker interface for all domain events.
 *
 * Domain events MUST be:
 *   - Immutable (use readonly properties)
 *   - Named in past tense (e.g. PizzaCooked, not CookPizza)
 *   - Carrying only the data handlers need (no entire aggregates)
 *
 * Events implementing this interface are automatically routed to the
 * async_events transport — see config/packages/messenger.yaml.
 */
interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredOn(): \DateTimeImmutable;
}
