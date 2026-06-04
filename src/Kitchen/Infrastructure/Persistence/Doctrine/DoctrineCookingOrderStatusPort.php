<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine;

use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusPort;
use App\Kitchen\Application\GetCookingOrderStatus\CookingOrderStatusView;
use Doctrine\DBAL\Connection;

final class DoctrineCookingOrderStatusPort implements CookingOrderStatusPort
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function findById(string $id): ?CookingOrderStatusView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, customer_order_id, recipe_id, status, started_at, completed_at
             FROM kitchen_cooking_orders
             WHERE id = :id',
            ['id' => $id],
        );

        return $row !== false ? $this->toView($row) : null;
    }

    /**
     * @return list<CookingOrderStatusView>
     */
    public function findByCustomerOrderId(string $customerOrderId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, customer_order_id, recipe_id, status, started_at, completed_at
             FROM kitchen_cooking_orders
             WHERE customer_order_id = :customerOrderId
             ORDER BY id ASC',
            ['customerOrderId' => $customerOrderId],
        );

        return array_map($this->toView(...), $rows);
    }

    private function toView(array $row): CookingOrderStatusView
    {
        return new CookingOrderStatusView(
            id: $row['id'],
            customerOrderId: $row['customer_order_id'],
            recipeId: $row['recipe_id'],
            status: $row['status'],
            startedAt: $row['started_at'],
            completedAt: $row['completed_at'],
        );
    }
}
