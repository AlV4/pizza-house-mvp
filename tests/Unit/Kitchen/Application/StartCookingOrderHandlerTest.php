<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Application\StartCookingOrder\StartCookingOrder;
use App\Kitchen\Application\StartCookingOrder\StartCookingOrderHandler;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\CookingOrderCreated;
use App\Kitchen\Domain\Event\CookingOrderStarted;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
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

final class StartCookingOrderHandlerTest extends TestCase
{
    private const RECIPE_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';
    private const ORDER_ULID  = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';

    private RecipeRepository&MockObject $recipes;
    private CookingOrderRepository&MockObject $cookingOrders;
    private EventBus&MockObject $eventBus;
    private StartCookingOrderHandler $handler;

    protected function setUp(): void
    {
        $this->recipes       = $this->createMock(RecipeRepository::class);
        $this->cookingOrders = $this->createMock(CookingOrderRepository::class);
        $this->eventBus      = $this->createMock(EventBus::class);
        $this->handler       = new StartCookingOrderHandler(
            $this->recipes,
            $this->cookingOrders,
            $this->eventBus,
        );
    }

    public function test_creates_order_starts_cooking_saves_and_publishes_both_events(): void
    {
        $this->recipes->method('findById')->willReturn($this->aRecipe());
        $this->cookingOrders->expects(self::once())->method('save');

        $publishedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent ...$events) use (&$publishedEvents): void {
                $publishedEvents = $events;
            });

        ($this->handler)(new StartCookingOrder(
            id: self::ORDER_ULID,
            customerOrderId: 'cust-order-1',
            recipeId: self::RECIPE_ULID,
        ));

        self::assertCount(2, $publishedEvents);

        $types = array_map(static fn (DomainEvent $e): string => $e::class, $publishedEvents);
        self::assertContains(CookingOrderCreated::class, $types);
        self::assertContains(CookingOrderStarted::class, $types);
    }

    public function test_throws_recipe_not_found_when_recipe_does_not_exist(): void
    {
        $this->recipes->method('findById')->willReturn(null);
        $this->cookingOrders->expects(self::never())->method('save');
        $this->eventBus->expects(self::never())->method('publish');

        $this->expectException(RecipeNotFoundException::class);

        ($this->handler)(new StartCookingOrder(
            id: self::ORDER_ULID,
            customerOrderId: 'cust-order-1',
            recipeId: self::RECIPE_ULID,
        ));
    }

    private function aRecipe(): Recipe
    {
        return Recipe::create(
            new RecipeId(self::RECIPE_ULID),
            new RecipeName('Margherita'),
            [new IngredientRequirement('Mozzarella', 100.0, Unit::Gram)],
            new Money(1299, 'EUR'),
            15,
        );
    }
}
