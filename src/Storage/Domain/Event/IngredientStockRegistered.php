<?php

declare(strict_types=1);

namespace App\Storage\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class IngredientStockRegistered implements DomainEvent
{
    public function __construct(
        public string $stockId,
        public string $ingredientName,
        public string $unit,
        public float $initialQuantity,
        public float $threshold,
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
