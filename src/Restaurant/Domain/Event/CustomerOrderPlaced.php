<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Event;

use App\Shared\Domain\DomainEvent;

final readonly class CustomerOrderPlaced implements DomainEvent
{
    /**
     * @param list<array{itemId: string, recipeId: string, quantity: int, pricePerUnit: int, currency: string}> $items
     */
    public function __construct(
        public string $customerOrderId,
        public string $customerName,
        public string $customerPhone,
        public array $items,
        public int $totalAmount,
        public string $currency,
        public \DateTimeImmutable $placedAt,
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
