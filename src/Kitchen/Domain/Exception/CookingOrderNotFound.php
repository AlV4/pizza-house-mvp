<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Exception;

use App\Kitchen\Domain\ValueObject\CookingOrderId;

final class CookingOrderNotFound extends \RuntimeException
{
    public static function withId(CookingOrderId|string $id): self
    {
        $value = $id instanceof CookingOrderId ? $id->value() : $id;

        return new self(sprintf('Cooking order with id "%s" was not found.', $value));
    }
}
