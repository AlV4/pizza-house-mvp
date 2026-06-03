<?php

declare(strict_types=1);

namespace App\Storage\Domain\Exception;

use App\Storage\Domain\ValueObject\IngredientName;
use App\Storage\Domain\ValueObject\StockId;

final class StockNotFound extends \RuntimeException
{
    public static function withId(StockId|string $id): self
    {
        $value = $id instanceof StockId ? $id->value() : $id;

        return new self(sprintf('Stock with id "%s" was not found.', $value));
    }

    public static function withIngredientName(IngredientName|string $name): self
    {
        $value = $name instanceof IngredientName ? $name->value() : $name;

        return new self(sprintf('Stock for ingredient "%s" was not found.', $value));
    }
}
