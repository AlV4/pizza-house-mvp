<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Event;

use App\Shared\Domain\DomainEvent;

/**
 * Cooking has begun for a cooking order.
 *
 * Carries the frozen ingredient list (from the order's RecipeSnapshot) so the
 * Storage context can consume stock without reaching back into Kitchen. Each
 * ingredient is a flat array: {name: string, quantity: float, unit: string}.
 */
final readonly class CookingOrderStarted implements DomainEvent
{
    /**
     * @param list<array{name: string, quantity: float, unit: string}> $ingredients
     */
    public function __construct(
        public string $cookingOrderId,
        public string $customerOrderId,
        public string $recipeId,
        public array $ingredients,
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
