<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\ValueObject;

use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Unit;
use PHPUnit\Framework\TestCase;

final class IngredientRequirementTest extends TestCase
{
    public function test_trims_surrounding_whitespace_from_the_name(): void
    {
        $requirement = new IngredientRequirement('  Mozzarella  ', 100.0, Unit::Gram);

        self::assertSame('Mozzarella', $requirement->name());
    }

    public function test_rejects_an_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IngredientRequirement('', 100.0, Unit::Gram);
    }

    public function test_rejects_a_whitespace_only_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IngredientRequirement('   ', 100.0, Unit::Gram);
    }

    public function test_rejects_a_zero_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IngredientRequirement('Mozzarella', 0.0, Unit::Gram);
    }

    public function test_rejects_a_negative_quantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IngredientRequirement('Mozzarella', -1.0, Unit::Gram);
    }

    public function test_equals_is_true_when_name_quantity_and_unit_all_match(): void
    {
        $a = new IngredientRequirement('Mozzarella', 100.0, Unit::Gram);
        $b = new IngredientRequirement('Mozzarella', 100.0, Unit::Gram);

        self::assertTrue($a->equals($b));
    }

    public function test_equals_is_false_when_the_quantity_differs(): void
    {
        $a = new IngredientRequirement('Mozzarella', 100.0, Unit::Gram);
        $b = new IngredientRequirement('Mozzarella', 200.0, Unit::Gram);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_when_the_unit_differs(): void
    {
        $a = new IngredientRequirement('Mozzarella', 100.0, Unit::Gram);
        $b = new IngredientRequirement('Mozzarella', 100.0, Unit::Kilogram);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_when_the_name_differs(): void
    {
        $a = new IngredientRequirement('Mozzarella', 100.0, Unit::Gram);
        $b = new IngredientRequirement('Basil', 100.0, Unit::Gram);

        self::assertFalse($a->equals($b));
    }
}
