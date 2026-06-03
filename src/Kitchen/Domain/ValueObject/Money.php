<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\ValueObject;

final readonly class Money
{
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/';

    public function __construct(
        public int $amount,
        public string $currency,
    ) {
        if (preg_match(self::CURRENCY_PATTERN, $currency) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid ISO-4217 currency code.', $currency)
            );
        }
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
