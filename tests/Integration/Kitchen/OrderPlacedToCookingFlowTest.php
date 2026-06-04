<?php

declare(strict_types=1);

namespace App\Tests\Integration\Kitchen;

use App\Kitchen\Application\CreateRecipe\CreateRecipe;
use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use App\Kitchen\Application\GetCookingOrderStatus\GetCookingOrderStatus;
use App\Kitchen\Application\ListCookingOrdersByCustomer\ListCookingOrdersByCustomer;
use App\Kitchen\Domain\Event\CookingOrderStarted;
use App\Shared\Domain\Event\CustomerOrderPlaced;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Uid\Ulid;

final class OrderPlacedToCookingFlowTest extends IntegrationTestCase
{
    public function test_one_cooking_order_is_started_per_unit_carrying_recipe_ingredients(): void
    {
        $recipeId = $this->createRecipe(
            name: 'Margherita',
            ingredients: [
                ['name' => 'Mozzarella', 'quantity' => 150.0, 'unit' => 'g'],
                ['name' => 'Tomato Sauce', 'quantity' => 80.0, 'unit' => 'g'],
            ],
        );
        $customerOrderId = (string) new Ulid();

        $this->eventBus->publish($this->customerOrderPlaced(
            customerOrderId: $customerOrderId,
            items: [
                $this->item($recipeId, quantity: 2),
                $this->item($recipeId, quantity: 1),
            ],
        ));

        $started = $this->recordedEvents->ofType(CookingOrderStarted::class);
        self::assertCount(3, $started);

        $expectedIngredients = [
            ['name' => 'Mozzarella', 'quantity' => 150.0, 'unit' => 'g'],
            ['name' => 'Tomato Sauce', 'quantity' => 80.0, 'unit' => 'g'],
        ];
        foreach ($started as $event) {
            self::assertSame($recipeId, $event->recipeId);
            self::assertSame($customerOrderId, $event->customerOrderId);
            self::assertEqualsCanonicalizing($expectedIngredients, $event->ingredients);
        }
    }

    public function test_each_started_cooking_order_is_in_progress_when_queried_by_id(): void
    {
        $recipeId = $this->createRecipe(
            name: 'Pepperoni',
            ingredients: [['name' => 'Pepperoni', 'quantity' => 60.0, 'unit' => 'g']],
        );
        $customerOrderId = (string) new Ulid();

        $this->eventBus->publish($this->customerOrderPlaced(
            customerOrderId: $customerOrderId,
            items: [$this->item($recipeId, quantity: 2)],
        ));

        $started = $this->recordedEvents->ofType(CookingOrderStarted::class);
        self::assertCount(2, $started);

        foreach ($started as $event) {
            $view = $this->queryBus->ask(new GetCookingOrderStatus($event->cookingOrderId));
            self::assertInstanceOf(CookingOrderStatusView::class, $view);
            self::assertSame($event->cookingOrderId, $view->id);
            self::assertSame('IN_PROGRESS', $view->status);
            self::assertNotNull($view->startedAt);
        }
    }

    public function test_started_cooking_orders_are_queryable_by_customer_order_id(): void
    {
        $recipeId = $this->createRecipe(
            name: 'Hawaiian',
            ingredients: [['name' => 'Pineapple', 'quantity' => 70.0, 'unit' => 'g']],
        );
        $customerOrderId = (string) new Ulid();

        $this->eventBus->publish($this->customerOrderPlaced(
            customerOrderId: $customerOrderId,
            items: [
                $this->item($recipeId, quantity: 2),
                $this->item($recipeId, quantity: 1),
            ],
        ));

        $orders = $this->queryBus->ask(new ListCookingOrdersByCustomer($customerOrderId));

        self::assertCount(3, $orders);
        foreach ($orders as $order) {
            self::assertInstanceOf(CookingOrderStatusView::class, $order);
            self::assertSame($customerOrderId, $order->customerOrderId);
            self::assertSame($recipeId, $order->recipeId);
            self::assertSame('IN_PROGRESS', $order->status);
        }
    }

    public function test_unknown_recipe_surfaces_recipe_not_found(): void
    {
        $unknownRecipeId = (string) new Ulid();
        $customerOrderId = (string) new Ulid();

        try {
            $this->eventBus->publish($this->customerOrderPlaced(
                customerOrderId: $customerOrderId,
                items: [$this->item($unknownRecipeId, quantity: 1)],
            ));
            self::fail('Expected a RecipeNotFoundException to surface.');
        } catch (\Throwable $thrown) {
            self::assertTrue(
                $this->chainContains($thrown, RecipeNotFoundException::class),
                sprintf('Exception chain did not contain %s.', RecipeNotFoundException::class),
            );
        }
    }

    /**
     * @param list<array{name: string, quantity: float, unit: string}> $ingredients
     */
    private function createRecipe(string $name, array $ingredients): string
    {
        $recipeId = (string) new Ulid();

        $this->commandBus->dispatch(new CreateRecipe(
            id: $recipeId,
            name: $name,
            ingredients: $ingredients,
            priceAmount: 1200,
            priceCurrency: 'USD',
            cookingTimeMinutes: 12,
        ));

        $this->recordedEvents->clear();

        return $recipeId;
    }

    /**
     * @return array{itemId: string, recipeId: string, quantity: int, pricePerUnit: int, currency: string}
     */
    private function item(string $recipeId, int $quantity): array
    {
        return [
            'itemId' => (string) new Ulid(),
            'recipeId' => $recipeId,
            'quantity' => $quantity,
            'pricePerUnit' => 1200,
            'currency' => 'USD',
        ];
    }

    /**
     * @param list<array{itemId: string, recipeId: string, quantity: int, pricePerUnit: int, currency: string}> $items
     */
    private function customerOrderPlaced(string $customerOrderId, array $items): CustomerOrderPlaced
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['pricePerUnit'] * $item['quantity'];
        }

        return new CustomerOrderPlaced(
            customerOrderId: $customerOrderId,
            customerName: 'Ada Lovelace',
            customerPhone: '+15551234567',
            items: $items,
            totalAmount: $total,
            currency: 'USD',
            placedAt: new \DateTimeImmutable(),
        );
    }

    private function chainContains(\Throwable $throwable, string $type): bool
    {
        for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof $type) {
                return true;
            }
        }

        return false;
    }
}
