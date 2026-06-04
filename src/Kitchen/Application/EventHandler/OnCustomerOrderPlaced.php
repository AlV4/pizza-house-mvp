<?php

declare(strict_types=1);

namespace App\Kitchen\Application\EventHandler;

use App\Kitchen\Application\StartCookingOrder\StartCookingOrder;
use App\Shared\Domain\Event\CustomerOrderPlaced;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'event.bus')]
final class OnCustomerOrderPlaced
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(CustomerOrderPlaced $event): void
    {
        foreach ($event->items as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $this->commandBus->dispatch(new StartCookingOrder(
                    id: (string) new Ulid(),
                    customerOrderId: $event->customerOrderId,
                    recipeId: $item['recipeId'],
                ));
            }
        }
    }
}
