<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Repository;

use App\Restaurant\Domain\Aggregate\CustomerOrder;
use App\Restaurant\Domain\ValueObject\CustomerOrderId;
use App\Restaurant\Domain\ValueObject\OrderStatus;

interface CustomerOrderRepository
{
    public function findById(CustomerOrderId $id): ?CustomerOrder;

    /**
     * @return list<CustomerOrder>
     */
    public function findByStatus(OrderStatus $status): array;

    public function save(CustomerOrder $order): void;
}
