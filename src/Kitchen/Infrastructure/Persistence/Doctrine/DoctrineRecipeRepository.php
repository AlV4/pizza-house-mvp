<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine;

use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRecipeRepository implements RecipeRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(RecipeId $id): ?Recipe
    {
        return $this->em->find(Recipe::class, $id->value());
    }

    public function findByName(RecipeName $name): ?Recipe
    {
        return $this->em->createQuery(
            'SELECT r FROM App\Kitchen\Domain\Aggregate\Recipe r WHERE r.name.value = :name'
        )
            ->setParameter('name', $name->value())
            ->getOneOrNullResult();
    }

    public function save(Recipe $recipe): void
    {
        $this->em->persist($recipe);
    }

    public function remove(Recipe $recipe): void
    {
        $this->em->remove($recipe);
    }
}
