<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\ValueObject;

use App\Kitchen\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_accepts_a_valid_iso_4217_currency_code(): void
    {
        $money = new Money(1299, 'EUR');

        self::assertSame('EUR', $money->currency());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedCurrencyProvider(): iterable
    {
        yield 'lowercase' => ['eur'];
        yield 'two letters' => ['EU'];
        yield 'four letters' => ['EURO'];
    }

    #[DataProvider('malformedCurrencyProvider')]
    public function test_rejects_a_malformed_currency_code(string $currency): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(1299, $currency);
    }

    public function test_is_positive_is_true_for_an_amount_above_zero(): void
    {
        $money = new Money(1, 'EUR');

        self::assertTrue($money->isPositive());
    }

    public function test_is_positive_is_false_for_a_zero_amount(): void
    {
        $money = new Money(0, 'EUR');

        self::assertFalse($money->isPositive());
    }

    public function test_is_positive_is_false_for_a_negative_amount(): void
    {
        $money = new Money(-1, 'EUR');

        self::assertFalse($money->isPositive());
    }

    public function test_equals_is_true_when_amount_and_currency_both_match(): void
    {
        $a = new Money(1299, 'EUR');
        $b = new Money(1299, 'EUR');

        self::assertTrue($a->equals($b));
    }

    public function test_equals_is_false_when_only_the_currency_differs(): void
    {
        $a = new Money(1299, 'EUR');
        $b = new Money(1299, 'USD');

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_when_only_the_amount_differs(): void
    {
        $a = new Money(1299, 'EUR');
        $b = new Money(1300, 'EUR');

        self::assertFalse($a->equals($b));
    }
}
