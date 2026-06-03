<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Exception;

use App\Restaurant\Domain\ValueObject\OrderItemId;

final class ItemAlreadyFullyReady extends \DomainException
{
    public static function forItem(OrderItemId|string $itemId): self
    {
        $value = $itemId instanceof OrderItemId ? $itemId->value() : $itemId;

        return new self(
            sprintf('Order item "%s" already has all its units marked ready.', $value)
        );
    }
}
