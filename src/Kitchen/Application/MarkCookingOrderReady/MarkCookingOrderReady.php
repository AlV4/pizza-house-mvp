<?php

declare(strict_types=1);

namespace App\Kitchen\Application\MarkCookingOrderReady;

final readonly class MarkCookingOrderReady
{
    public function __construct(
        public string $cookingOrderId,
    ) {
    }
}
