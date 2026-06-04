<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusPort;
use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use App\Kitchen\Application\ListCookingOrdersByCustomer\ListCookingOrdersByCustomer;
use App\Kitchen\Application\ListCookingOrdersByCustomer\ListCookingOrdersByCustomerHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListCookingOrdersByCustomerHandlerTest extends TestCase
{
    private const ORDER_ULID_1 = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';
    private const ORDER_ULID_2 = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';
    private const RECIPE_ULID  = '01HZX9P3K8Q7R6S5T4V3W2Y1Y0';

    private CookingOrderStatusPort&MockObject $port;
    private ListCookingOrdersByCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->port    = $this->createMock(CookingOrderStatusPort::class);
        $this->handler = new ListCookingOrdersByCustomerHandler($this->port);
    }

    public function test_returns_empty_array_when_port_has_no_orders_for_customer(): void
    {
        $this->port->method('findByCustomerOrderId')->willReturn([]);

        $result = ($this->handler)(new ListCookingOrdersByCustomer(customerOrderId: 'cust-order-1'));

        self::assertSame([], $result);
    }

    public function test_returns_all_views_from_port(): void
    {
        $views = [
            new CookingOrderStatusView(self::ORDER_ULID_1, 'cust-order-1', self::RECIPE_ULID, 'PENDING', null, null),
            new CookingOrderStatusView(self::ORDER_ULID_2, 'cust-order-1', self::RECIPE_ULID, 'PENDING', null, null),
        ];

        $this->port
            ->method('findByCustomerOrderId')
            ->with('cust-order-1')
            ->willReturn($views);

        $result = ($this->handler)(new ListCookingOrdersByCustomer(customerOrderId: 'cust-order-1'));

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(CookingOrderStatusView::class, $result);
        self::assertSame(self::ORDER_ULID_1, $result[0]->id);
        self::assertSame(self::ORDER_ULID_2, $result[1]->id);
    }

    public function test_view_fields_match_port_data(): void
    {
        $startedAt = '2024-01-15T10:30:00+00:00';

        $views = [
            new CookingOrderStatusView(
                id: self::ORDER_ULID_1,
                customerOrderId: 'cust-order-1',
                recipeId: self::RECIPE_ULID,
                status: 'IN_PROGRESS',
                startedAt: $startedAt,
                completedAt: null,
            ),
        ];

        $this->port->method('findByCustomerOrderId')->willReturn($views);

        $result = ($this->handler)(new ListCookingOrdersByCustomer(customerOrderId: 'cust-order-1'));

        $view = $result[0];
        self::assertSame(self::ORDER_ULID_1, $view->id);
        self::assertSame('cust-order-1', $view->customerOrderId);
        self::assertSame(self::RECIPE_ULID, $view->recipeId);
        self::assertSame('IN_PROGRESS', $view->status);
        self::assertSame($startedAt, $view->startedAt);
        self::assertNull($view->completedAt);
    }
}
