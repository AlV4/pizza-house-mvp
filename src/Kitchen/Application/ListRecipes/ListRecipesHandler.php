<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ListRecipes;

use App\Kitchen\Application\GetRecipe\RecipeView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListRecipesHandler
{
    public function __construct(
        private readonly RecipeListPort $port,
    ) {
    }

    /**
     * @return list<RecipeView>
     */
    public function __invoke(ListRecipes $query): array
    {
        return $this->port->all();
    }
}
