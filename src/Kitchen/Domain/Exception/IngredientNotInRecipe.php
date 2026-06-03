<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Exception;

final class IngredientNotInRecipe extends \DomainException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Ingredient "%s" is not part of the recipe.', $name));
    }
}
