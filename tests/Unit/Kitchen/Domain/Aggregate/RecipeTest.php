<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\Aggregate;

use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\RecipeCreated;
use App\Kitchen\Domain\Event\RecipeIngredientAdded;
use App\Kitchen\Domain\Event\RecipeIngredientRemoved;
use App\Kitchen\Domain\Event\RecipePriceChanged;
use App\Kitchen\Domain\Exception\DuplicateIngredient;
use App\Kitchen\Domain\Exception\IngredientNotInRecipe;
use App\Kitchen\Domain\Exception\RecipeMustHaveAtLeastOneIngredient;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use PHPUnit\Framework\TestCase;

final class RecipeTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    public function test_create_records_a_single_recipe_created_event_with_expected_payload(): void
    {
        $recipe = Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            15,
        );

        $events = $recipe->pullDomainEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(RecipeCreated::class, $event);
        self::assertSame(self::VALID_ULID, $event->recipeId);
        self::assertSame('Margherita', $event->name);
        self::assertSame(1299, $event->priceAmount);
        self::assertSame('EUR', $event->priceCurrency);
        self::assertSame(self::VALID_ULID, $event->aggregateId());
    }

    public function test_create_throws_when_ingredient_list_is_empty(): void
    {
        $this->expectException(RecipeMustHaveAtLeastOneIngredient::class);

        Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [],
            new Money(1299, 'EUR'),
            15,
        );
    }

    public function test_create_throws_when_ingredient_names_collide_case_and_whitespace_insensitively(): void
    {
        $this->expectException(DuplicateIngredient::class);

        Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [
                $this->ingredient('Mozzarella'),
                $this->ingredient('  mozzarella '),
            ],
            new Money(1299, 'EUR'),
            15,
        );
    }

    public function test_create_throws_when_price_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(0, 'EUR'),
            15,
        );
    }

    public function test_create_throws_when_price_is_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(-1, 'EUR'),
            15,
        );
    }

    public function test_create_throws_when_cooking_time_is_below_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            0,
        );
    }

    public function test_create_throws_when_cooking_time_is_above_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            121,
        );
    }

    public function test_create_accepts_cooking_time_at_the_lower_boundary(): void
    {
        $recipe = Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            1,
        );

        self::assertSame(1, $recipe->cookingTimeMinutes());
    }

    public function test_create_accepts_cooking_time_at_the_upper_boundary(): void
    {
        $recipe = Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            120,
        );

        self::assertSame(120, $recipe->cookingTimeMinutes());
    }

    public function test_add_ingredient_appends_it_and_records_event_carrying_the_unit_string(): void
    {
        $recipe = $this->aRecipe();
        $recipe->pullDomainEvents();

        $recipe->addIngredient($this->ingredient('Basil', 5.0, Unit::Gram));

        $names = array_map(
            static fn (IngredientRequirement $ingredient): string => $ingredient->name(),
            $recipe->ingredients()
        );
        self::assertContains('Basil', $names);

        $events = $recipe->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(RecipeIngredientAdded::class, $event);
        self::assertSame('Basil', $event->ingredientName);
        self::assertSame(5.0, $event->quantity);
        self::assertSame('g', $event->unit);
    }

    public function test_add_ingredient_throws_when_name_duplicates_an_existing_one_case_insensitively(): void
    {
        $recipe = $this->aRecipe();

        $this->expectException(DuplicateIngredient::class);

        $recipe->addIngredient($this->ingredient('  MOZZARELLA '));
    }

    public function test_remove_ingredient_drops_it_and_records_event(): void
    {
        $recipe = Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella'), $this->ingredient('Basil')],
            new Money(1299, 'EUR'),
            15,
        );
        $recipe->pullDomainEvents();

        $recipe->removeIngredient('Basil');

        $names = array_map(
            static fn (IngredientRequirement $ingredient): string => $ingredient->name(),
            $recipe->ingredients()
        );
        self::assertNotContains('Basil', $names);

        $events = $recipe->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(RecipeIngredientRemoved::class, $event);
        self::assertSame('Basil', $event->ingredientName);
    }

    public function test_remove_ingredient_throws_when_the_name_is_not_present(): void
    {
        $recipe = $this->aRecipe();

        $this->expectException(IngredientNotInRecipe::class);

        $recipe->removeIngredient('Pepperoni');
    }

    public function test_remove_ingredient_throws_when_removing_the_last_remaining_ingredient(): void
    {
        $recipe = $this->aRecipe();

        $this->expectException(RecipeMustHaveAtLeastOneIngredient::class);

        $recipe->removeIngredient('Mozzarella');
    }

    public function test_change_price_records_event_with_old_and_new_amounts(): void
    {
        $recipe = $this->aRecipe();
        $recipe->pullDomainEvents();

        $recipe->changePrice(new Money(1599, 'EUR'));

        self::assertTrue($recipe->price()->equals(new Money(1599, 'EUR')));

        $events = $recipe->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(RecipePriceChanged::class, $event);
        self::assertSame(1299, $event->oldAmount);
        self::assertSame(1599, $event->newAmount);
        self::assertSame('EUR', $event->currency);
    }

    public function test_change_price_is_a_no_op_when_the_new_price_equals_the_current_one(): void
    {
        $recipe = $this->aRecipe();
        $recipe->pullDomainEvents();

        $recipe->changePrice(new Money(1299, 'EUR'));

        self::assertSame([], $recipe->pullDomainEvents());
        self::assertTrue($recipe->price()->equals(new Money(1299, 'EUR')));
    }

    public function test_change_price_throws_when_the_new_price_is_not_positive(): void
    {
        $recipe = $this->aRecipe();

        $this->expectException(\InvalidArgumentException::class);

        $recipe->changePrice(new Money(0, 'EUR'));
    }

    public function test_pull_domain_events_clears_the_buffer_on_subsequent_calls(): void
    {
        $recipe = $this->aRecipe();

        $first = $recipe->pullDomainEvents();
        $second = $recipe->pullDomainEvents();

        self::assertCount(1, $first);
        self::assertSame([], $second);
    }

    private function aRecipe(): Recipe
    {
        return Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [$this->ingredient('Mozzarella')],
            new Money(1299, 'EUR'),
            15,
        );
    }

    private function ingredient(
        string $name,
        float $quantity = 100.0,
        Unit $unit = Unit::Gram,
    ): IngredientRequirement {
        return new IngredientRequirement($name, $quantity, $unit);
    }
}
