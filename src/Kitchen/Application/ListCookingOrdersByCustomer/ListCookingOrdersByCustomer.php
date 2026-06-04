<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ListCookingOrdersByCustomer;

final readonly class ListCookingOrdersByCustomer
{
    public function __construct(
        public string $customerOrderId,
    ) {
    }
}
