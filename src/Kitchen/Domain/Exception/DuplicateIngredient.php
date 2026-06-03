<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Exception;

final class DuplicateIngredient extends \DomainException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Ingredient "%s" is already present in the recipe.', $name));
    }
}
