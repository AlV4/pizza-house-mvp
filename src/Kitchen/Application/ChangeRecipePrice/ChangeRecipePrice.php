<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ChangeRecipePrice;

final readonly class ChangeRecipePrice
{
    public function __construct(
        public string $recipeId,
        public int $newAmount,
        public string $currency,
    ) {
    }
}
