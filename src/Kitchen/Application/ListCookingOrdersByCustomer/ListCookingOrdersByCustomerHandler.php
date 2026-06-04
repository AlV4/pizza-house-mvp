<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ListCookingOrdersByCustomer;

use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusPort;
use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCookingOrdersByCustomerHandler
{
    public function __construct(
        private readonly CookingOrderStatusPort $port,
    ) {
    }

    /**
     * @return list<CookingOrderStatusView>
     */
    public function __invoke(ListCookingOrdersByCustomer $query): array
    {
        return $this->port->findByCustomerOrderId($query->customerOrderId);
    }
}
