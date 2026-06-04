<?php

declare(strict_types=1);

namespace App\Kitchen\Application\StartCookingOrder;

use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StartCookingOrderHandler
{
    public function __construct(
        private readonly RecipeRepository $recipes,
        private readonly CookingOrderRepository $cookingOrders,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(StartCookingOrder $command): void
    {
        $recipe = $this->recipes->findById(new RecipeId($command->recipeId));

        if ($recipe === null) {
            throw RecipeNotFoundException::withId($command->recipeId);
        }

        $order = CookingOrder::create(
            new CookingOrderId($command->id),
            $command->customerOrderId,
            $recipe,
        );

        $order->startCooking();

        $this->cookingOrders->save($order);
        $this->eventBus->publish(...$order->pullDomainEvents());
    }
}
