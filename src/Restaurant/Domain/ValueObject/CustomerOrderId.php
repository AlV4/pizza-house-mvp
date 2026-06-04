<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\ValueObject;

final readonly class CustomerOrderId
{
    private const ULID_PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::ULID_PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid ULID for a CustomerOrderId.', $value)
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
