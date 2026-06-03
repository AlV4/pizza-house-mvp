<?php

declare(strict_types=1);

namespace App\Storage\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class IngredientStockDepleted implements DomainEvent
{
    public function __construct(
        public string $stockId,
        public string $ingredientName,
        public float $availableQuantity,
        public float $threshold,
        public string $unit,
        public \DateTimeImmutable $occurredOn = new \DateTimeImmutable(),
    ) {
    }

    public function aggregateId(): string
    {
        return $this->stockId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
