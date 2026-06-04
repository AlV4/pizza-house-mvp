<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\AddIngredientToRecipe\AddIngredientToRecipe;
use App\Kitchen\Application\AddIngredientToRecipe\AddIngredientToRecipeHandler;
use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\RecipeIngredientAdded;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddIngredientToRecipeHandlerTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    private RecipeRepository&MockObject $recipes;
    private EventBus&MockObject $eventBus;
    private AddIngredientToRecipeHandler $handler;

    protected function setUp(): void
    {
        $this->recipes  = $this->createMock(RecipeRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->handler  = new AddIngredientToRecipeHandler($this->recipes, $this->eventBus);
    }

    public function test_loads_recipe_saves_and_publishes_recipe_ingredient_added(): void
    {
        $recipe = $this->aRecipe();
        $recipe->pullDomainEvents(); // clear creation event

        $this->recipes->method('findById')->willReturn($recipe);
        $this->recipes->expects(self::once())->method('save')->with($recipe);

        $publishedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent ...$events) use (&$publishedEvents): void {
                $publishedEvents = $events;
            });

        ($this->handler)(new AddIngredientToRecipe(
            recipeId: self::VALID_ULID,
            name: 'Basil',
            quantity: 5.0,
            unit: 'g',
        ));

        self::assertCount(1, $publishedEvents);
        self::assertInstanceOf(RecipeIngredientAdded::class, $publishedEvents[0]);
        self::assertSame('Basil', $publishedEvents[0]->ingredientName);
        self::assertSame(5.0, $publishedEvents[0]->quantity);
        self::assertSame('g', $publishedEvents[0]->unit);
    }

    public function test_throws_recipe_not_found_when_recipe_does_not_exist(): void
    {
        $this->recipes->method('findById')->willReturn(null);
        $this->recipes->expects(self::never())->method('save');
        $this->eventBus->expects(self::never())->method('publish');

        $this->expectException(RecipeNotFoundException::class);

        ($this->handler)(new AddIngredientToRecipe(
            recipeId: self::VALID_ULID,
            name: 'Basil',
            quantity: 5.0,
            unit: 'g',
        ));
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
