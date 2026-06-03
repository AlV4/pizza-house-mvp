<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class CustomerOrderReady implements DomainEvent
{
    public function __construct(
        public string $customerOrderId,
        public \DateTimeImmutable $readyAt,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {
    }

    public function aggregateId(): string
    {
        return $this->customerOrderId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
