<?php

declare(strict_types=1);

namespace App\Storage\Domain\ValueObject;

use App\Storage\Domain\Exception\InsufficientStock;
use App\Storage\Domain\Exception\UnitMismatch;

final readonly class Quantity
{
    public function __construct(
        public float $value,
        public Unit $unit,
    ) {
        if ($value < 0.0) {
            throw new \InvalidArgumentException(
                sprintf('Quantity must be non-negative, got %f.', $value)
            );
        }
    }

    public function value(): float
    {
        return $this->value;
    }

    public function unit(): Unit
    {
        return $this->unit;
    }

    public function add(self $other): self
    {
        $this->assertSameUnit($other);

        return new self($this->value + $other->value, $this->unit);
    }

    public function subtract(self $other): self
    {
        $this->assertSameUnit($other);

        if ($other->value > $this->value) {
            throw InsufficientStock::cannotSubtract($this, $other);
        }

        return new self($this->value - $other->value, $this->unit);
    }

    public function equals(self $other): bool
    {
        return $this->unit === $other->unit && $this->value === $other->value;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameUnit($other);

        return $this->value < $other->value;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertSameUnit($other);

        return $this->value >= $other->value;
    }

    private function assertSameUnit(self $other): void
    {
        if ($this->unit !== $other->unit) {
            throw UnitMismatch::between($this->unit, $other->unit);
        }
    }
}
