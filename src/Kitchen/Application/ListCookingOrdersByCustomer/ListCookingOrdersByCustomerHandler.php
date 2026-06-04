<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ListCookingOrdersByCustomer;

use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCookingOrdersByCustomerHandler
{
    public function __construct(
        private readonly CookingOrderRepository $cookingOrders,
    ) {
    }

    /**
     * @return list<CookingOrderStatusView>
     */
    public function __invoke(ListCookingOrdersByCustomer $query): array
    {
        $orders = $this->cookingOrders->findByCustomerOrderId($query->customerOrderId);

        return array_map(fn (CookingOrder $order): CookingOrderStatusView => new CookingOrderStatusView(
            id: $order->id()->value(),
            customerOrderId: $order->customerOrderId(),
            recipeId: $order->recipeId()->value(),
            status: $order->status()->value,
            startedAt: $order->startedAt()?->format(\DateTimeInterface::ATOM),
            completedAt: $order->completedAt()?->format(\DateTimeInterface::ATOM),
        ), $orders);
    }
}
