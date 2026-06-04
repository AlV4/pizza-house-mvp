<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\Exception\CookingOrderNotFoundException;
use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusPort;
use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use App\Kitchen\Application\GetCookingOrderStatus\GetCookingOrderStatus;
use App\Kitchen\Application\GetCookingOrderStatus\GetCookingOrderStatusHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetCookingOrderStatusHandlerTest extends TestCase
{
    private const ORDER_ULID  = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';
    private const RECIPE_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Z0';

    private CookingOrderStatusPort&MockObject $port;
    private GetCookingOrderStatusHandler $handler;

    protected function setUp(): void
    {
        $this->port    = $this->createMock(CookingOrderStatusPort::class);
        $this->handler = new GetCookingOrderStatusHandler($this->port);
    }

    public function test_returns_view_with_correct_fields_for_pending_order(): void
    {
        $view = new CookingOrderStatusView(
            id: self::ORDER_ULID,
            customerOrderId: 'cust-order-1',
            recipeId: self::RECIPE_ULID,
            status: 'PENDING',
            startedAt: null,
            completedAt: null,
        );

        $this->port->method('findById')->willReturn($view);

        $result = ($this->handler)(new GetCookingOrderStatus(cookingOrderId: self::ORDER_ULID));

        self::assertSame(self::ORDER_ULID, $result->id);
        self::assertSame('cust-order-1', $result->customerOrderId);
        self::assertSame(self::RECIPE_ULID, $result->recipeId);
        self::assertSame('PENDING', $result->status);
        self::assertNull($result->startedAt);
        self::assertNull($result->completedAt);
    }

    public function test_returns_view_with_timestamps_for_in_progress_order(): void
    {
        $startedAt = '2024-01-15T10:30:00+00:00';

        $view = new CookingOrderStatusView(
            id: self::ORDER_ULID,
            customerOrderId: 'cust-order-1',
            recipeId: self::RECIPE_ULID,
            status: 'IN_PROGRESS',
            startedAt: $startedAt,
            completedAt: null,
        );

        $this->port->method('findById')->willReturn($view);

        $result = ($this->handler)(new GetCookingOrderStatus(cookingOrderId: self::ORDER_ULID));

        self::assertSame('IN_PROGRESS', $result->status);
        self::assertSame($startedAt, $result->startedAt);
        self::assertNull($result->completedAt);
    }

    public function test_throws_cooking_order_not_found_when_port_returns_null(): void
    {
        $this->port->method('findById')->willReturn(null);

        $this->expectException(CookingOrderNotFoundException::class);

        ($this->handler)(new GetCookingOrderStatus(cookingOrderId: self::ORDER_ULID));
    }
}
