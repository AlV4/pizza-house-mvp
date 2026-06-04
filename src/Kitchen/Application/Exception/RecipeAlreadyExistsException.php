<?php

declare(strict_types=1);

namespace App\Kitchen\Application\Exception;

final class RecipeAlreadyExistsException extends \DomainException
{
    public static function withName(string $name): self
    {
        return new self(sprintf('A recipe named "%s" already exists.', $name));
    }
}
