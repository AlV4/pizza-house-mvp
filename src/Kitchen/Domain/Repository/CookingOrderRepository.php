<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Repository;

use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Domain\ValueObject\CookingOrderId;

interface CookingOrderRepository
{
    public function findById(CookingOrderId $id): ?CookingOrder;

    /**
     * @return list<CookingOrder>
     */
    public function findByCustomerOrderId(string $customerOrderId): array;

    public function save(CookingOrder $cookingOrder): void;
}
