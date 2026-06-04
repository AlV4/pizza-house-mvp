<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine\Type;

use App\Kitchen\Domain\ValueObject\RecipeId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class RecipeIdType extends StringType
{
    public const NAME = 'kitchen_recipe_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RecipeId
    {
        if ($value === null) {
            return null;
        }

        return new RecipeId((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RecipeId) {
            return $value->value();
        }

        return (string) $value;
    }
}
