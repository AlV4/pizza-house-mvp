<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class CookingOrderCreated implements DomainEvent
{
    public function __construct(
        public string $cookingOrderId,
        public string $customerOrderId,
        public string $recipeId,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {
    }

    public function aggregateId(): string
    {
        return $this->cookingOrderId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
