<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\ValueObject;

final readonly class IngredientRequirement
{
    public string $name;

    public function __construct(
        string $name,
        public float $quantity,
        public Unit $unit,
    ) {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Ingredient name must not be empty.');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                sprintf('Ingredient quantity must be greater than 0, got %s.', $quantity)
            );
        }

        $this->name = $trimmed;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function unit(): Unit
    {
        return $this->unit;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->quantity === $other->quantity
            && $this->unit === $other->unit;
    }
}
