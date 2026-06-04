<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\DomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Test-only event-bus handler that records every published domain event into
 * {@see RecordedDomainEvents}.
 *
 * It handles the DomainEvent interface, so Messenger invokes it for every
 * concrete event dispatched on the event bus. Because the event bus allows
 * multiple handlers (and no senders) this coexists with the real handlers
 * (e.g. OnCustomerOrderPlaced) without interfering with them.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class RecordingEventSubscriber
{
    public function __construct(
        private RecordedDomainEvents $recorded,
    ) {
    }

    public function __invoke(DomainEvent $event): void
    {
        $this->recorded->record($event);
    }
}
