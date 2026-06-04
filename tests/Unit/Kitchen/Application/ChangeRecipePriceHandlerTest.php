<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\ChangeRecipePrice\ChangeRecipePrice;
use App\Kitchen\Application\ChangeRecipePrice\ChangeRecipePriceHandler;
use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\RecipePriceChanged;
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

final class ChangeRecipePriceHandlerTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    private RecipeRepository&MockObject $recipes;
    private EventBus&MockObject $eventBus;
    private ChangeRecipePriceHandler $handler;

    protected function setUp(): void
    {
        $this->recipes  = $this->createMock(RecipeRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->handler  = new ChangeRecipePriceHandler($this->recipes, $this->eventBus);
    }

    public function test_saves_recipe_and_publishes_recipe_price_changed_on_happy_path(): void
    {
        $recipe = $this->aRecipe();
        $recipe->pullDomainEvents();

        $this->recipes->method('findById')->willReturn($recipe);
        $this->recipes->expects(self::once())->method('save')->with($recipe);

        $publishedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent ...$events) use (&$publishedEvents): void {
                $publishedEvents = $events;
            });

        ($this->handler)(new ChangeRecipePrice(
            recipeId: self::VALID_ULID,
            newAmount: 1599,
            currency: 'EUR',
        ));

        self::assertCount(1, $publishedEvents);
        self::assertInstanceOf(RecipePriceChanged::class, $publishedEvents[0]);
        self::assertSame(1299, $publishedEvents[0]->oldAmount);
        self::assertSame(1599, $publishedEvents[0]->newAmount);
        self::assertSame('EUR', $publishedEvents[0]->currency);
    }

    public function test_throws_recipe_not_found_when_recipe_does_not_exist(): void
    {
        $this->recipes->method('findById')->willReturn(null);
        $this->recipes->expects(self::never())->method('save');
        $this->eventBus->expects(self::never())->method('publish');

        $this->expectException(RecipeNotFoundException::class);

        ($this->handler)(new ChangeRecipePrice(
            recipeId: self::VALID_ULID,
            newAmount: 1599,
            currency: 'EUR',
        ));
    }

    public function test_publishes_no_event_when_price_is_unchanged(): void
    {
        $recipe = $this->aRecipe();
        $recipe->pullDomainEvents();

        $this->recipes->method('findById')->willReturn($recipe);
        $this->recipes->expects(self::once())->method('save');

        $publishedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent ...$events) use (&$publishedEvents): void {
                $publishedEvents = $events;
            });

        // same amount and currency as the recipe fixture (1299 EUR)
        ($this->handler)(new ChangeRecipePrice(
            recipeId: self::VALID_ULID,
            newAmount: 1299,
            currency: 'EUR',
        ));

        self::assertSame([], $publishedEvents);
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
