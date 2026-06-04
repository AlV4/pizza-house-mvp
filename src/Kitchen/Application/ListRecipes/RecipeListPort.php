<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ListRecipes;

use App\Kitchen\Application\GetRecipe\RecipeView;

interface RecipeListPort
{
    /**
     * @return list<RecipeView>
     */
    public function all(): array;
}
