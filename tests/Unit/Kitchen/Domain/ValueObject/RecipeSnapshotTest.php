<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\ValueObject;

use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\RecipeSnapshot;
use App\Kitchen\Domain\ValueObject\Unit;
use PHPUnit\Framework\TestCase;

final class RecipeSnapshotTest extends TestCase
{
    private const RECIPE_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';

    public function test_trims_the_name(): void
    {
        $snapshot = new RecipeSnapshot('  Margherita  ', [$this->ingredient('Mozzarella')], 15);

        self::assertSame('Margherita', $snapshot->name());
    }

    public function test_rejects_an_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeSnapshot('', [$this->ingredient('Mozzarella')], 15);
    }

    public function test_rejects_a_whitespace_only_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeSnapshot('   ', [$this->ingredient('Mozzarella')], 15);
    }

    public function test_rejects_an_empty_ingredient_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeSnapshot('Margherita', [], 15);
    }

    public function test_rejects_a_cooking_time_below_one_minute(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 0);
    }

    public function test_rejects_a_cooking_time_above_the_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 121);
    }

    public function test_from_recipe_copies_name_ingredients_and_cooking_time(): void
    {
        $recipe = $this->aRecipe();

        $snapshot = RecipeSnapshot::fromRecipe($recipe);

        self::assertSame('Margherita', $snapshot->name());
        self::assertSame(15, $snapshot->cookingTimeMinutes());
        self::assertCount(1, $snapshot->ingredients());
        self::assertSame('Mozzarella', $snapshot->ingredients()[0]->name());
    }

    public function test_equals_is_true_when_name_cooking_time_and_ingredients_all_match(): void
    {
        $a = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 15);
        $b = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 15);

        self::assertTrue($a->equals($b));
    }

    public function test_equals_is_false_when_the_name_differs(): void
    {
        $a = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 15);
        $b = new RecipeSnapshot('Marinara', [$this->ingredient('Mozzarella')], 15);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_when_the_cooking_time_differs(): void
    {
        $a = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 15);
        $b = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 20);

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_when_the_ingredient_count_differs(): void
    {
        $a = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella')], 15);
        $b = new RecipeSnapshot(
            'Margherita',
            [$this->ingredient('Mozzarella'), $this->ingredient('Basil')],
            15,
        );

        self::assertFalse($a->equals($b));
    }

    public function test_equals_is_false_when_an_ingredient_differs(): void
    {
        $a = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella', 100.0)], 15);
        $b = new RecipeSnapshot('Margherita', [$this->ingredient('Mozzarella', 200.0)], 15);

        self::assertFalse($a->equals($b));
    }

    private function aRecipe(): Recipe
    {
        return Recipe::create(
            new RecipeId(self::RECIPE_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            15,
        );
    }

    private function ingredient(string $name, float $quantity = 100.0): IngredientRequirement
    {
        return new IngredientRequirement($name, $quantity, Unit::Gram);
    }
}
