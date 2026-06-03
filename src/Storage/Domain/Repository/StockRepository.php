<?php

declare(strict_types=1);

namespace App\Storage\Domain\Repository;

use App\Storage\Domain\Aggregate\Stock;
use App\Storage\Domain\ValueObject\IngredientName;
use App\Storage\Domain\ValueObject\StockId;

interface StockRepository
{
    public function findById(StockId $id): ?Stock;

    public function findByIngredientName(IngredientName $name): ?Stock;

    /**
     * @return list<Stock>
     */
    public function findAllBelowThreshold(): array;

    public function save(Stock $stock): void;

    public function remove(Stock $stock): void;
}
