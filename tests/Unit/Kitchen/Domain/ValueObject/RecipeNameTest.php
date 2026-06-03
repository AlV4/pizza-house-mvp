<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\ValueObject;

use App\Kitchen\Domain\ValueObject\RecipeName;
use PHPUnit\Framework\TestCase;

final class RecipeNameTest extends TestCase
{
    public function test_trims_surrounding_whitespace_and_stores_the_trimmed_value(): void
    {
        $name = new RecipeName('  Margherita  ');

        self::assertSame('Margherita', $name->value());
    }

    public function test_rejects_a_name_shorter_than_the_minimum_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeName('A');
    }

    public function test_accepts_a_name_at_the_minimum_length(): void
    {
        $name = new RecipeName('Pi');

        self::assertSame('Pi', $name->value());
    }

    public function test_accepts_a_name_at_the_maximum_length(): void
    {
        $eighty = str_repeat('a', 80);

        $name = new RecipeName($eighty);

        self::assertSame($eighty, $name->value());
    }

    public function test_rejects_a_name_longer_than_the_maximum_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeName(str_repeat('a', 81));
    }

    public function test_equals_is_true_for_the_same_trimmed_value(): void
    {
        $a = new RecipeName('Margherita');
        $b = new RecipeName('  Margherita ');

        self::assertTrue($a->equals($b));
    }

    public function test_equals_is_false_for_different_values(): void
    {
        $a = new RecipeName('Margherita');
        $b = new RecipeName('Pepperoni');

        self::assertFalse($a->equals($b));
    }
}
