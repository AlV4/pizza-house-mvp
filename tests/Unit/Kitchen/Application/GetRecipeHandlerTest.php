<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Application\GetRecipe\GetRecipe;
use App\Kitchen\Application\GetRecipe\GetRecipeHandler;
use App\Kitchen\Application\GetRecipe\RecipeView;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetRecipeHandlerTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    private RecipeRepository&MockObject $recipes;
    private GetRecipeHandler $handler;

    protected function setUp(): void
    {
        $this->recipes = $this->createMock(RecipeRepository::class);
        $this->handler = new GetRecipeHandler($this->recipes);
    }

    public function test_returns_recipe_view_with_correct_scalar_fields(): void
    {
        $this->recipes->method('findById')->willReturn($this->aRecipe());

        $view = ($this->handler)(new GetRecipe(recipeId: self::VALID_ULID));

        self::assertInstanceOf(RecipeView::class, $view);
        self::assertSame(self::VALID_ULID, $view->id);
        self::assertSame('Margherita', $view->name);
        self::assertSame(1299, $view->priceAmount);
        self::assertSame('EUR', $view->priceCurrency);
        self::assertSame(15, $view->cookingTimeMinutes);
    }

    public function test_returns_recipe_view_with_mapped_ingredients(): void
    {
        $this->recipes->method('findById')->willReturn($this->aRecipe());

        $view = ($this->handler)(new GetRecipe(recipeId: self::VALID_ULID));

        self::assertCount(1, $view->ingredients);
        self::assertSame('Mozzarella', $view->ingredients[0]['name']);
        self::assertSame(100.0, $view->ingredients[0]['quantity']);
        self::assertSame('g', $view->ingredients[0]['unit']);
    }

    public function test_throws_recipe_not_found_when_recipe_does_not_exist(): void
    {
        $this->recipes->method('findById')->willReturn(null);

        $this->expectException(RecipeNotFoundException::class);

        ($this->handler)(new GetRecipe(recipeId: self::VALID_ULID));
    }

    private function aRecipe(): Recipe
    {
        return Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [new IngredientRequirement('Mozzarella', 100.0, Unit::Gram)],
            new Money(1299, 'EUR'),
            15,
        );
    }
}
