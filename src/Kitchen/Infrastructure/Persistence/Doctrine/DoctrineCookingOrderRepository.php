<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine;

use App\Kitchen\Domain\Aggregate\CookingOrder;
use App\Kitchen\Domain\Repository\CookingOrderRepository;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCookingOrderRepository implements CookingOrderRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(CookingOrderId $id): ?CookingOrder
    {
        return $this->em->find(CookingOrder::class, $id->value());
    }

    /**
     * @return list<CookingOrder>
     */
    public function findByCustomerOrderId(string $customerOrderId): array
    {
        /** @var list<CookingOrder> */
        return $this->em->createQuery(
            'SELECT o FROM App\Kitchen\Domain\Aggregate\CookingOrder o WHERE o.customerOrderId = :customerOrderId'
        )
            ->setParameter('customerOrderId', $customerOrderId)
            ->getResult();
    }

    public function save(CookingOrder $cookingOrder): void
    {
        $this->em->persist($cookingOrder);
    }
}
