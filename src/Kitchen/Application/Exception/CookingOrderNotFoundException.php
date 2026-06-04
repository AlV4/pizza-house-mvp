<?php

declare(strict_types=1);

namespace App\Kitchen\Application\Exception;

final class CookingOrderNotFoundException extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Cooking order "%s" not found.', $id));
    }
}
