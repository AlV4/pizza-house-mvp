<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\ValueObject;

final readonly class CustomerInfo
{
    private const PHONE_PATTERN = '/^\+?[0-9]{7,15}$/';
    private const NAME_MIN_LENGTH = 2;
    private const NAME_MAX_LENGTH = 80;

    public string $name;
    public string $phone;

    public function __construct(string $name, string $phone)
    {
        $trimmedName = trim($name);
        $length = mb_strlen($trimmedName);

        if ($length < self::NAME_MIN_LENGTH || $length > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Customer name must be between %d and %d characters.',
                    self::NAME_MIN_LENGTH,
                    self::NAME_MAX_LENGTH,
                )
            );
        }

        if (preg_match(self::PHONE_PATTERN, $phone) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not a valid phone number.', $phone)
            );
        }

        $this->name = $trimmedName;
        $this->phone = $phone;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->phone === $other->phone;
    }
}
