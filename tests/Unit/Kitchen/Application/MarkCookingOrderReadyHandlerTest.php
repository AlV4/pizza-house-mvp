<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\Exception\CookingOrderNotFoundException;
use App\Kitchen\Application\MarkCookingOrderReady\MarkCookingOrderReady;
use App\Kitchen\Application\MarkCookingOrderReady\MarkCookingOrderReadyHandler;
use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\PizzaCooked;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MarkCookingOrderReadyHandlerTest extends TestCase
{
    private const ORDER_ULID  = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';
    private const RECIPE_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';

    private CookingOrderRepository&MockObject $cookingOrders;
    private EventBus&MockObject $eventBus;
    private MarkCookingOrderReadyHandler $handler;

    protected function setUp(): void
    {
        $this->cookingOrders = $this->createMock(CookingOrderRepository::class);
        $this->eventBus      = $this->createMock(EventBus::class);
        $this->handler       = new MarkCookingOrderReadyHandler($this->cookingOrders, $this->eventBus);
    }

    public function test_marks_order_ready_saves_and_publishes_pizza_cooked(): void
    {
        $order = $this->anInProgressOrder();

        $this->cookingOrders->method('findById')->willReturn($order);
        $this->cookingOrders->expects(self::once())->method('save')->with($order);

        $publishedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent ...$events) use (&$publishedEvents): void {
                $publishedEvents = $events;
            });

        ($this->handler)(new MarkCookingOrderReady(cookingOrderId: self::ORDER_ULID));

        self::assertCount(1, $publishedEvents);
        self::assertInstanceOf(PizzaCooked::class, $publishedEvents[0]);
        self::assertSame(self::ORDER_ULID, $publishedEvents[0]->cookingOrderId);
    }

    public function test_throws_cooking_order_not_found_when_order_does_not_exist(): void
    {
        $this->cookingOrders->method('findById')->willReturn(null);
        $this->cookingOrders->expects(self::never())->method('save');
        $this->eventBus->expects(self::never())->method('publish');

        $this->expectException(CookingOrderNotFoundException::class);

        ($this->handler)(new MarkCookingOrderReady(cookingOrderId: self::ORDER_ULID));
    }

    private function anInProgressOrder(): CookingOrder
    {
        $recipe = Recipe::create(
            new RecipeId(self::RECIPE_ULID),
            new RecipeName('Margherita'),
            [new IngredientRequirement('Mozzarella', 100.0, Unit::Gram)],
            new Money(1299, 'EUR'),
            15,
        );

        $order = CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            'cust-order-1',
            $recipe,
        );
        $order->startCooking();
        $order->pullDomainEvents(); // clear creation + started events

        return $order;
    }
}
