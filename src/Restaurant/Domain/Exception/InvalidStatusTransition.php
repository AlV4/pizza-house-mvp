<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Exception;

use App\Restaurant\Domain\ValueObject\OrderStatus;

final class InvalidStatusTransition extends \DomainException
{
    public static function fromTo(OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            sprintf(
                'Cannot transition a customer order from "%s" to "%s".',
                $from->value,
                $to->value,
            )
        );
    }
}
