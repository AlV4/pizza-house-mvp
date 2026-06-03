<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class RecipePriceChanged implements DomainEvent
{
    public function __construct(
        public string $recipeId,
        public int $oldAmount,
        public int $newAmount,
        public string $currency,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {
    }

    public function aggregateId(): string
    {
        return $this->recipeId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
