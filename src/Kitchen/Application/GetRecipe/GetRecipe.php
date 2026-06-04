<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetRecipe;

final readonly class GetRecipe
{
    public function __construct(
        public string $recipeId,
    ) {
    }
}
