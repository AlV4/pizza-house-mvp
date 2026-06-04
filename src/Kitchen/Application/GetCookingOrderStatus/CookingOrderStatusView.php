<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetCookingOrderStatus;

final readonly class CookingOrderStatusView
{
    public function __construct(
        public string $id,
        public string $customerOrderId,
        public string $recipeId,
        public string $status,
        public ?string $startedAt,
        public ?string $completedAt,
    ) {
    }
}
