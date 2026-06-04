<?php

declare(strict_types=1);

namespace App\Kitchen\Application\CreateRecipe;

final readonly class CreateRecipe
{
    /**
     * @param list<array{name: string, quantity: float, unit: string}> $ingredients
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $ingredients,
        public int $priceAmount,
        public string $priceCurrency,
        public int $cookingTimeMinutes,
    ) {
    }
}
