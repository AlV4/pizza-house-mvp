<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class RecipeCreated implements DomainEvent
{
    public function __construct(
        public string $recipeId,
        public string $name,
        public int $priceAmount,
        public string $priceCurrency,
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
