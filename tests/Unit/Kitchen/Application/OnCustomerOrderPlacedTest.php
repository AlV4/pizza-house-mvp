<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\EventHandler\OnCustomerOrderPlaced;
use App\Kitchen\Application\StartCookingOrder\StartCookingOrder;
use App\Shared\Domain\Event\CustomerOrderPlaced;
use App\Shared\Application\Bus\CommandBus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OnCustomerOrderPlacedTest extends TestCase
{
    private const RECIPE_ULID_A = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';
    private const RECIPE_ULID_B = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';

    private CommandBus&MockObject $commandBus;
    private OnCustomerOrderPlaced $handler;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBus::class);
        $this->handler    = new OnCustomerOrderPlaced($this->commandBus);
    }

    public function test_dispatches_one_command_when_single_item_with_quantity_one(): void
    {
        $event = $this->aCustomerOrderPlaced([
            ['itemId' => 'item-1', 'recipeId' => self::RECIPE_ULID_A, 'quantity' => 1, 'pricePerUnit' => 1299, 'currency' => 'EUR'],
        ]);

        $dispatched = [];
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $command) use (&$dispatched): void {
                $dispatched[] = $command;
            });

        ($this->handler)($event);

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(StartCookingOrder::class, $dispatched[0]);
        self::assertSame(self::RECIPE_ULID_A, $dispatched[0]->recipeId);
        self::assertSame('cust-order-1', $dispatched[0]->customerOrderId);
    }

    public function test_dispatches_n_commands_when_single_item_has_quantity_greater_than_one(): void
    {
        $event = $this->aCustomerOrderPlaced([
            ['itemId' => 'item-1', 'recipeId' => self::RECIPE_ULID_A, 'quantity' => 3, 'pricePerUnit' => 1299, 'currency' => 'EUR'],
        ]);

        $dispatched = [];
        $this->commandBus
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(static function (object $command) use (&$dispatched): void {
                $dispatched[] = $command;
            });

        ($this->handler)($event);

        self::assertCount(3, $dispatched);

        foreach ($dispatched as $command) {
            self::assertInstanceOf(StartCookingOrder::class, $command);
            self::assertSame(self::RECIPE_ULID_A, $command->recipeId);
        }

        // Each command must carry a distinct id
        $ids = array_map(static fn (StartCookingOrder $c): string => $c->id, $dispatched);
        self::assertCount(3, array_unique($ids));
    }

    public function test_dispatches_commands_for_every_item_times_its_quantity(): void
    {
        $event = $this->aCustomerOrderPlaced([
            ['itemId' => 'item-1', 'recipeId' => self::RECIPE_ULID_A, 'quantity' => 2, 'pricePerUnit' => 1299, 'currency' => 'EUR'],
            ['itemId' => 'item-2', 'recipeId' => self::RECIPE_ULID_B, 'quantity' => 1, 'pricePerUnit' => 999, 'currency' => 'EUR'],
        ]);

        $dispatched = [];
        $this->commandBus
            ->expects(self::exactly(3))
            ->method('dispatch')
            ->willReturnCallback(static function (object $command) use (&$dispatched): void {
                $dispatched[] = $command;
            });

        ($this->handler)($event);

        self::assertCount(3, $dispatched);

        $recipeIds = array_map(static fn (StartCookingOrder $c): string => $c->recipeId, $dispatched);
        self::assertSame(2, count(array_filter($recipeIds, static fn (string $id): bool => $id === self::RECIPE_ULID_A)));
        self::assertSame(1, count(array_filter($recipeIds, static fn (string $id): bool => $id === self::RECIPE_ULID_B)));
    }

    /**
     * @param list<array{itemId: string, recipeId: string, quantity: int, pricePerUnit: int, currency: string}> $items
     */
    private function aCustomerOrderPlaced(array $items): CustomerOrderPlaced
    {
        return new CustomerOrderPlaced(
            customerOrderId: 'cust-order-1',
            customerName: 'Alice',
            customerPhone: '+1234567890',
            items: $items,
            totalAmount: 1299,
            currency: 'EUR',
            placedAt: new \DateTimeImmutable('2024-01-01 12:00:00'),
        );
    }
}
