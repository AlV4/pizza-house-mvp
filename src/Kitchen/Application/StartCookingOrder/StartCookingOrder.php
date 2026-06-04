<?php

declare(strict_types=1);

namespace App\Kitchen\Application\StartCookingOrder;

final readonly class StartCookingOrder
{
    public function __construct(
        public string $id,
        public string $customerOrderId,
        public string $recipeId,
    ) {
    }
}
