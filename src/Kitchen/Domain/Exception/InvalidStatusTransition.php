<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Exception;

use App\Kitchen\Domain\ValueObject\CookingStatus;

final class InvalidStatusTransition extends \DomainException
{
    public static function fromTo(CookingStatus $from, CookingStatus $to): self
    {
        return new self(
            sprintf(
                'Cannot transition a cooking order from "%s" to "%s".',
                $from->value,
                $to->value,
            )
        );
    }
}
