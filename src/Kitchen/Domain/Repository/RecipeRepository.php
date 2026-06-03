<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Repository;

use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;

interface RecipeRepository
{
    public function findById(RecipeId $id): ?Recipe;

    public function findByName(RecipeName $name): ?Recipe;

    public function save(Recipe $recipe): void;

    public function remove(Recipe $recipe): void;
}
