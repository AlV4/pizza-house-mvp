<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Aggregate;

use App\Restaurant\Domain\Exception\ItemAlreadyFullyReady;
use App\Restaurant\Domain\ValueObject\Money;
use App\Restaurant\Domain\ValueObject\OrderItemId;

/**
 * A single line of a customer order: a recipe reference, a quantity and a
 * frozen unit-price snapshot.
 *
 * OrderItem is an entity that lives ONLY inside CustomerOrder. It is not an
 * aggregate root and records no events of its own — the owning CustomerOrder
 * is responsible for emitting OrderItemReady.
 *
 * The recipeId is kept as an opaque string on purpose: it references Kitchen's
 * RecipeId, but a typed cross-context import would couple the two domains.
 */
final class OrderItem
{
    private int $readyCount = 0;

    private function __construct(
        private readonly OrderItemId $itemId,
        private readonly string $recipeId,
        private readonly string $recipeName,
        private readonly int $quantity,
        private readonly Money $pricePerUnit,
    ) {
    }

    public static function create(
        OrderItemId $itemId,
        string $recipeId,
        string $recipeName,
        int $quantity,
        Money $pricePerUnit,
    ): self {
        $trimmedRecipeId = trim($recipeId);

        if ($trimmedRecipeId === '') {
            throw new \InvalidArgumentException('Recipe id must not be empty.');
        }

        $trimmedRecipeName = trim($recipeName);

        if ($trimmedRecipeName === '') {
            throw new \InvalidArgumentException('Recipe name must not be empty.');
        }

        if ($quantity < 1) {
            throw new \InvalidArgumentException('Order item quantity must be at least 1.');
        }

        if (!$pricePerUnit->isPositive()) {
            throw new \InvalidArgumentException('Order item price per unit must be positive.');
        }

        return new self(
            $itemId,
            $trimmedRecipeId,
            $trimmedRecipeName,
            $quantity,
            $pricePerUnit,
        );
    }

    /**
     * Records that $count more units of this item have been cooked.
     *
     * Throws if the increment would push readyCount beyond quantity — an
     * over-completion the order must never allow.
     */
    public function markReady(int $count = 1): void
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Ready count increment must be at least 1.');
        }

        if ($this->readyCount + $count > $this->quantity) {
            throw ItemAlreadyFullyReady::forItem($this->itemId);
        }

        $this->readyCount += $count;
    }

    public function isFullyReady(): bool
    {
        return $this->readyCount === $this->quantity;
    }

    public function lineTotal(): Money
    {
        return $this->pricePerUnit->multiply($this->quantity);
    }

    public function itemId(): OrderItemId
    {
        return $this->itemId;
    }

    public function recipeId(): string
    {
        return $this->recipeId;
    }

    public function recipeName(): string
    {
        return $this->recipeName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function pricePerUnit(): Money
    {
        return $this->pricePerUnit;
    }

    public function readyCount(): int
    {
        return $this->readyCount;
    }
}
