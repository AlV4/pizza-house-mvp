<?php

declare(strict_types=1);

namespace App\Storage\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class IngredientThresholdChanged implements DomainEvent
{
    public function __construct(
        public string $stockId,
        public float $oldThreshold,
        public float $newThreshold,
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
