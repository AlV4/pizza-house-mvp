<?php

declare(strict_types=1);

namespace App\Storage\Domain\Aggregate;

use App\Shared\Domain\AggregateRoot;
use App\Storage\Domain\Event\IngredientOutOfStock;
use App\Storage\Domain\Event\IngredientStockDepleted;
use App\Storage\Domain\Event\IngredientStockRegistered;
use App\Storage\Domain\Event\IngredientStockReplenished;
use App\Storage\Domain\Event\IngredientThresholdChanged;
use App\Storage\Domain\Exception\UnitMismatch;
use App\Storage\Domain\ValueObject\IngredientName;
use App\Storage\Domain\ValueObject\Quantity;
use App\Storage\Domain\ValueObject\StockId;
use App\Storage\Domain\ValueObject\Unit;

final class Stock extends AggregateRoot
{
    private function __construct(
        private readonly StockId $id,
        private readonly IngredientName $ingredientName,
        private readonly Unit $unit,
        private Quantity $availableQuantity,
        private Quantity $threshold,
    ) {
    }

    public static function register(
        StockId $id,
        IngredientName $ingredientName,
        Unit $unit,
        Quantity $initialQuantity,
        Quantity $threshold,
    ): self {
        if ($initialQuantity->unit() !== $unit) {
            throw UnitMismatch::between($unit, $initialQuantity->unit());
        }

        if ($threshold->unit() !== $unit) {
            throw UnitMismatch::between($unit, $threshold->unit());
        }

        $stock = new self($id, $ingredientName, $unit, $initialQuantity, $threshold);

        $stock->recordEvent(new IngredientStockRegistered(
            $id->value(),
            $ingredientName->value(),
            $unit->value,
            $initialQuantity->value(),
            $threshold->value(),
        ));

        return $stock;
    }

    public function addDelivery(Quantity $delivered): void
    {
        $this->assertSameUnit($delivered);

        $this->availableQuantity = $this->availableQuantity->add($delivered);

        $this->recordEvent(new IngredientStockReplenished(
            $this->id->value(),
            $this->ingredientName->value(),
            $delivered->value(),
            $this->unit->value,
            $this->availableQuantity->value(),
        ));
    }

    public function consume(Quantity $requested): void
    {
        $this->assertSameUnit($requested);

        if ($this->availableQuantity->isLessThan($requested)) {
            $this->recordEvent(new IngredientOutOfStock(
                $this->id->value(),
                $this->ingredientName->value(),
                $requested->value(),
                $this->availableQuantity->value(),
                $this->unit->value,
            ));

            return;
        }

        $wasAtOrAboveThreshold = $this->availableQuantity->isGreaterThanOrEqual($this->threshold);

        $this->availableQuantity = $this->availableQuantity->subtract($requested);

        $isBelowThreshold = $this->availableQuantity->isLessThan($this->threshold);

        if ($wasAtOrAboveThreshold && $isBelowThreshold) {
            $this->recordEvent(new IngredientStockDepleted(
                $this->id->value(),
                $this->ingredientName->value(),
                $this->availableQuantity->value(),
                $this->threshold->value(),
                $this->unit->value,
            ));
        }
    }

    public function adjustThreshold(Quantity $newThreshold): void
    {
        $this->assertSameUnit($newThreshold);

        $oldThreshold = $this->threshold->value();
        $this->threshold = $newThreshold;

        $this->recordEvent(new IngredientThresholdChanged(
            $this->id->value(),
            $oldThreshold,
            $newThreshold->value(),
        ));
    }

    public function id(): StockId
    {
        return $this->id;
    }

    public function ingredientName(): IngredientName
    {
        return $this->ingredientName;
    }

    public function unit(): Unit
    {
        return $this->unit;
    }

    public function availableQuantity(): Quantity
    {
        return $this->availableQuantity;
    }

    public function threshold(): Quantity
    {
        return $this->threshold;
    }

    private function assertSameUnit(Quantity $quantity): void
    {
        if ($quantity->unit() !== $this->unit) {
            throw UnitMismatch::between($this->unit, $quantity->unit());
        }
    }
}
