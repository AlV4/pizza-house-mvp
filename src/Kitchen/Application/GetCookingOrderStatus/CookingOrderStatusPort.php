<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetCookingOrderStatus;

interface CookingOrderStatusPort
{
    public function findById(string $id): ?CookingOrderStatusView;

    /**
     * @return list<CookingOrderStatusView>
     */
    public function findByCustomerOrderId(string $customerOrderId): array;
}
