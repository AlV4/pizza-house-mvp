<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Exception;

use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;

final class RecipeNotFound extends \RuntimeException
{
    public static function withId(RecipeId|string $id): self
    {
        $value = $id instanceof RecipeId ? $id->value() : $id;

        return new self(sprintf('Recipe with id "%s" was not found.', $value));
    }

    public static function withName(RecipeName|string $name): self
    {
        $value = $name instanceof RecipeName ? $name->value() : $name;

        return new self(sprintf('Recipe with name "%s" was not found.', $value));
    }
}
