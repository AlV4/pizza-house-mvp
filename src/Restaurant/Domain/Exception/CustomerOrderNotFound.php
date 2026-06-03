<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Exception;

use App\Restaurant\Domain\ValueObject\CustomerOrderId;

final class CustomerOrderNotFound extends \RuntimeException
{
    public static function withId(CustomerOrderId|string $id): self
    {
        $value = $id instanceof CustomerOrderId ? $id->value() : $id;

        return new self(sprintf('Customer order with id "%s" was not found.', $value));
    }
}
