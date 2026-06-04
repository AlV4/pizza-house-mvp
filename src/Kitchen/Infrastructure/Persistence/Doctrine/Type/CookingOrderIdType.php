<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine\Type;

use App\Kitchen\Domain\ValueObject\CookingOrderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class CookingOrderIdType extends StringType
{
    public const NAME = 'kitchen_cooking_order_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CookingOrderId
    {
        if ($value === null) {
            return null;
        }

        return new CookingOrderId((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CookingOrderId) {
            return $value->value();
        }

        return (string) $value;
    }
}
