<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use App\Shared\Domain\DomainEvent;

/**
 * Publishes domain events asynchronously to zero or more handlers.
 *
 * Events are routed to the async_events Messenger transport (Doctrine-backed).
 * Handlers run in the messenger-consumer worker container.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
