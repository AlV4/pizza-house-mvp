<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine\Type;

use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Unit;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class IngredientsType extends Type
{
    public const NAME = 'kitchen_ingredients';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * @return list<IngredientRequirement>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $data = is_string($value)
            ? json_decode($value, true, flags: JSON_THROW_ON_ERROR)
            : $value;

        return array_map(
            static fn (array $item): IngredientRequirement => new IngredientRequirement(
                $item['name'],
                (float) $item['quantity'],
                Unit::from($item['unit']),
            ),
            $data,
        );
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!is_array($value)) {
            return '[]';
        }

        return json_encode(
            array_map(
                static fn (IngredientRequirement $ingredient): array => [
                    'name' => $ingredient->name(),
                    'quantity' => $ingredient->quantity(),
                    'unit' => $ingredient->unit()->value,
                ],
                $value,
            ),
            JSON_THROW_ON_ERROR,
        );
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
