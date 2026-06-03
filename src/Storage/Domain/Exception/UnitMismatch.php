<?php

declare(strict_types=1);

namespace App\Storage\Domain\Exception;

use App\Storage\Domain\ValueObject\Unit;

final class UnitMismatch extends \DomainException
{
    public static function between(Unit $expected, Unit $actual): self
    {
        return new self(sprintf(
            'Unit mismatch: expected "%s" but got "%s".',
            $expected->value,
            $actual->value
        ));
    }
}
