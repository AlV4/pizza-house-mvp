<?php

declare(strict_types=1);

namespace App\Storage\Domain\Exception;

use App\Storage\Domain\ValueObject\Quantity;

final class InsufficientStock extends \DomainException
{
    public static function cannotSubtract(Quantity $available, Quantity $requested): self
    {
        return new self(sprintf(
            'Cannot subtract %f%s from %f%s: result would be negative.',
            $requested->value(),
            $requested->unit()->value,
            $available->value(),
            $available->unit()->value
        ));
    }
}
