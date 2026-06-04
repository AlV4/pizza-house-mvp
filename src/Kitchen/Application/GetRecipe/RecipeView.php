<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetRecipe;

final readonly class RecipeView
{
    /**
     * @param list<array{name: string, quantity: float, unit: string}> $ingredients
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $priceAmount,
        public string $priceCurrency,
        public int $cookingTimeMinutes,
        public array $ingredients,
    ) {
    }
}
