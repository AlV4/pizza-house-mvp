<?php

declare(strict_types=1);

namespace App\Kitchen\Application\AddIngredientToRecipe;

final readonly class AddIngredientToRecipe
{
    public function __construct(
        public string $recipeId,
        public string $name,
        public float $quantity,
        public string $unit,
    ) {
    }
}
