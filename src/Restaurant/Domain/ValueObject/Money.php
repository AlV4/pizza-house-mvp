<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\ValueObject;

/**
 * Money value object.
 *
 * This is a deliberate duplicate of Kitchen's Money. Extracting a shared kernel
 * is a documented non-goal of the MVP (see docs/restaurant.md). Do not import
 * Kitchen's Money here.
 */
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

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot add money in different currencies: "%s" and "%s".',
                    $this->currency,
                    $other->currency,
                )
            );
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
