<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\ValueObject;

final readonly class RecipeName
{
    private const MIN_LENGTH = 2;
    private const MAX_LENGTH = 80;

    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Recipe name must be between %d and %d characters, got %d.',
                    self::MIN_LENGTH,
                    self::MAX_LENGTH,
                    $length
                )
            );
        }

        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
