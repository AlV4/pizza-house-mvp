<?php

declare(strict_types=1);

namespace App\Kitchen\Application\MarkCookingOrderReady;

use App\Kitchen\Application\Exception\CookingOrderNotFoundException;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class MarkCookingOrderReadyHandler
{
    public function __construct(
        private readonly CookingOrderRepository $cookingOrders,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(MarkCookingOrderReady $command): void
    {
        $order = $this->cookingOrders->findById(new CookingOrderId($command->cookingOrderId));

        if ($order === null) {
            throw CookingOrderNotFoundException::withId($command->cookingOrderId);
        }

        $order->markAsReady();

        $this->cookingOrders->save($order);
        $this->eventBus->publish(...$order->pullDomainEvents());
    }
}
