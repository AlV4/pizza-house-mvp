<?php

declare(strict_types=1);

namespace App\Kitchen\Application\Exception;

final class RecipeNotFoundException extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Recipe "%s" not found.', $id));
    }
}
