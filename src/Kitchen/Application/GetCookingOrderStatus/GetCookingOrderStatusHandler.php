<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetCookingOrderStatus;

use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Application\Exception\CookingOrderNotFoundException;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetCookingOrderStatusHandler
{
    public function __construct(
        private readonly CookingOrderRepository $cookingOrders,
    ) {
    }

    public function __invoke(GetCookingOrderStatus $query): CookingOrderStatusView
    {
        $order = $this->cookingOrders->findById(new CookingOrderId($query->cookingOrderId));

        if ($order === null) {
            throw CookingOrderNotFoundException::withId($query->cookingOrderId);
        }

        return $this->toView($order);
    }

    private function toView(CookingOrder $order): CookingOrderStatusView
    {
        return new CookingOrderStatusView(
            id: $order->id()->value(),
            customerOrderId: $order->customerOrderId(),
            recipeId: $order->recipeId()->value(),
            status: $order->status()->value,
            startedAt: $order->startedAt()?->format(\DateTimeInterface::ATOM),
            completedAt: $order->completedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
