<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Exception;

final class RecipeMustHaveAtLeastOneIngredient extends \DomainException
{
    public static function create(): self
    {
        return new self('A recipe must have at least one ingredient.');
    }
}
