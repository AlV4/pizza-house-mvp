<?php

declare(strict_types=1);

namespace App\Kitchen\Application\CancelCookingOrder;

final readonly class CancelCookingOrder
{
    public function __construct(
        public string $cookingOrderId,
        public string $reason,
    ) {
    }
}
