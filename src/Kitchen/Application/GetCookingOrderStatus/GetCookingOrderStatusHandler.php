<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetCookingOrderStatus;

use App\Kitchen\Application\Exception\CookingOrderNotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetCookingOrderStatusHandler
{
    public function __construct(
        private readonly CookingOrderStatusPort $port,
    ) {
    }

    public function __invoke(GetCookingOrderStatus $query): CookingOrderStatusView
    {
        $view = $this->port->findById($query->cookingOrderId);

        if ($view === null) {
            throw CookingOrderNotFoundException::withId($query->cookingOrderId);
        }

        return $view;
    }
}
