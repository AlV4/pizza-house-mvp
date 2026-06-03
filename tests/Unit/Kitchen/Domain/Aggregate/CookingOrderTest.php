<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\Aggregate;

use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\CookingOrderCancelled;
use App\Kitchen\Domain\Event\CookingOrderCreated;
use App\Kitchen\Domain\Event\CookingOrderStarted;
use App\Kitchen\Domain\Event\PizzaCooked;
use App\Kitchen\Domain\Exception\InvalidStatusTransition;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use App\Kitchen\Domain\ValueObject\CookingStatus;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use PHPUnit\Framework\TestCase;

final class CookingOrderTest extends TestCase
{
    private const ORDER_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';
    private const RECIPE_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';

    public function test_create_starts_pending_with_no_timestamps(): void
    {
        $order = $this->anOrder();

        self::assertSame(CookingStatus::Pending, $order->status());
        self::assertNull($order->startedAt());
        self::assertNull($order->completedAt());
    }

    public function test_create_records_a_single_cooking_order_created_event_with_expected_payload(): void
    {
        $order = CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            'customer-order-42',
            $this->aRecipe(),
        );

        $events = $order->pullDomainEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(CookingOrderCreated::class, $event);
        self::assertSame(self::ORDER_ULID, $event->cookingOrderId);
        self::assertSame('customer-order-42', $event->customerOrderId);
        self::assertSame(self::RECIPE_ULID, $event->recipeId);
        self::assertSame(self::ORDER_ULID, $event->aggregateId());
    }

    public function test_create_freezes_the_recipe_into_a_snapshot_without_price(): void
    {
        $recipe = $this->aRecipe();

        $order = CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            'customer-order-42',
            $recipe,
        );

        $snapshot = $order->recipeSnapshot();
        self::assertSame('Margherita', $snapshot->name());
        self::assertSame(15, $snapshot->cookingTimeMinutes());
        self::assertCount(1, $snapshot->ingredients());
        self::assertSame('Mozzarella', $snapshot->ingredients()[0]->name());
        self::assertFalse(method_exists($snapshot, 'price'));
    }

    public function test_create_trims_the_customer_order_id(): void
    {
        $order = CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            '  customer-order-42  ',
            $this->aRecipe(),
        );

        self::assertSame('customer-order-42', $order->customerOrderId());
    }

    public function test_create_throws_when_customer_order_id_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            '',
            $this->aRecipe(),
        );
    }

    public function test_create_throws_when_customer_order_id_is_whitespace_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            '   ',
            $this->aRecipe(),
        );
    }

    public function test_start_cooking_moves_pending_to_in_progress_and_sets_started_at(): void
    {
        $order = $this->anOrder();
        $order->pullDomainEvents();

        $order->startCooking();

        self::assertSame(CookingStatus::InProgress, $order->status());
        self::assertNotNull($order->startedAt());
        self::assertNull($order->completedAt());
    }

    public function test_start_cooking_records_started_event_carrying_the_snapshot_ingredients(): void
    {
        $order = $this->anOrder();
        $order->pullDomainEvents();

        $order->startCooking();

        $events = $order->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(CookingOrderStarted::class, $event);
        self::assertSame(self::ORDER_ULID, $event->cookingOrderId);
        self::assertSame('customer-order-42', $event->customerOrderId);
        self::assertSame(self::RECIPE_ULID, $event->recipeId);
        self::assertSame(
            [['name' => 'Mozzarella', 'quantity' => 100.0, 'unit' => 'g']],
            $event->ingredients,
        );
    }

    public function test_start_cooking_is_a_no_op_when_already_in_progress(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $startedAt = $order->startedAt();
        $order->pullDomainEvents();

        $order->startCooking();

        self::assertSame([], $order->pullDomainEvents());
        self::assertSame($startedAt, $order->startedAt());
    }

    public function test_start_cooking_throws_when_order_is_ready(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $order->markAsReady();

        $this->expectException(InvalidStatusTransition::class);

        $order->startCooking();
    }

    public function test_start_cooking_throws_when_order_is_cancelled(): void
    {
        $order = $this->anOrder();
        $order->cancel('out of stock');

        $this->expectException(InvalidStatusTransition::class);

        $order->startCooking();
    }

    public function test_mark_as_ready_moves_in_progress_to_ready_and_sets_completed_at(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $order->pullDomainEvents();

        $order->markAsReady();

        self::assertSame(CookingStatus::Ready, $order->status());
        self::assertNotNull($order->completedAt());
    }

    public function test_mark_as_ready_records_pizza_cooked_event(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $order->pullDomainEvents();

        $order->markAsReady();

        $events = $order->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(PizzaCooked::class, $event);
        self::assertSame(self::ORDER_ULID, $event->cookingOrderId);
        self::assertSame('customer-order-42', $event->customerOrderId);
        self::assertSame(self::RECIPE_ULID, $event->recipeId);
    }

    public function test_mark_as_ready_is_a_no_op_when_already_ready(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $order->markAsReady();
        $completedAt = $order->completedAt();
        $order->pullDomainEvents();

        $order->markAsReady();

        self::assertSame([], $order->pullDomainEvents());
        self::assertSame($completedAt, $order->completedAt());
    }

    public function test_mark_as_ready_throws_when_order_is_still_pending(): void
    {
        $order = $this->anOrder();

        $this->expectException(InvalidStatusTransition::class);

        $order->markAsReady();
    }

    public function test_mark_as_ready_throws_when_order_is_cancelled(): void
    {
        $order = $this->anOrder();
        $order->cancel('customer left');

        $this->expectException(InvalidStatusTransition::class);

        $order->markAsReady();
    }

    public function test_cancel_moves_pending_to_cancelled_and_records_event(): void
    {
        $order = $this->anOrder();
        $order->pullDomainEvents();

        $order->cancel('out of stock');

        self::assertSame(CookingStatus::Cancelled, $order->status());

        $events = $order->pullDomainEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(CookingOrderCancelled::class, $event);
        self::assertSame(self::ORDER_ULID, $event->cookingOrderId);
        self::assertSame('customer-order-42', $event->customerOrderId);
        self::assertSame('out of stock', $event->reason);
    }

    public function test_cancel_moves_in_progress_to_cancelled(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $order->pullDomainEvents();

        $order->cancel('oven broke');

        self::assertSame(CookingStatus::Cancelled, $order->status());
        self::assertInstanceOf(CookingOrderCancelled::class, $order->pullDomainEvents()[0]);
    }

    public function test_cancel_does_not_set_completed_at(): void
    {
        $order = $this->anOrder();
        $order->startCooking();

        $order->cancel('oven broke');

        self::assertNull($order->completedAt());
    }

    public function test_cancel_trims_the_reason(): void
    {
        $order = $this->anOrder();
        $order->pullDomainEvents();

        $order->cancel('  out of stock  ');

        $event = $order->pullDomainEvents()[0];
        self::assertInstanceOf(CookingOrderCancelled::class, $event);
        self::assertSame('out of stock', $event->reason);
    }

    public function test_cancel_is_a_no_op_when_already_cancelled(): void
    {
        $order = $this->anOrder();
        $order->cancel('out of stock');
        $order->pullDomainEvents();

        $order->cancel('changed mind');

        self::assertSame([], $order->pullDomainEvents());
        self::assertSame(CookingStatus::Cancelled, $order->status());
    }

    public function test_cancel_throws_when_order_is_ready(): void
    {
        $order = $this->anOrder();
        $order->startCooking();
        $order->markAsReady();

        $this->expectException(InvalidStatusTransition::class);

        $order->cancel('too late');
    }

    public function test_cancel_throws_when_reason_is_empty(): void
    {
        $order = $this->anOrder();

        $this->expectException(\InvalidArgumentException::class);

        $order->cancel('   ');
    }

    public function test_snapshot_is_unaffected_by_later_changes_to_the_live_recipe(): void
    {
        $recipe = $this->aRecipe();
        $order = CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            'customer-order-42',
            $recipe,
        );

        $recipe->changePrice(new Money(9999, 'EUR'));
        $recipe->addIngredient(new IngredientRequirement('Basil', 5.0, Unit::Gram));

        $snapshot = $order->recipeSnapshot();
        self::assertCount(1, $snapshot->ingredients());
        self::assertSame('Mozzarella', $snapshot->ingredients()[0]->name());
    }

    public function test_started_event_payload_is_unaffected_by_later_recipe_edits(): void
    {
        $recipe = $this->aRecipe();
        $order = CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            'customer-order-42',
            $recipe,
        );
        $order->pullDomainEvents();

        $recipe->addIngredient(new IngredientRequirement('Basil', 5.0, Unit::Gram));
        $order->startCooking();

        $event = $order->pullDomainEvents()[0];
        self::assertInstanceOf(CookingOrderStarted::class, $event);
        self::assertSame(
            [['name' => 'Mozzarella', 'quantity' => 100.0, 'unit' => 'g']],
            $event->ingredients,
        );
    }

    private function anOrder(): CookingOrder
    {
        return CookingOrder::create(
            new CookingOrderId(self::ORDER_ULID),
            'customer-order-42',
            $this->aRecipe(),
        );
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
