<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetCookingOrderStatus;

final readonly class GetCookingOrderStatus
{
    public function __construct(
        public string $cookingOrderId,
    ) {
    }
}
