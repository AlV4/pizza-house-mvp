<?php

declare(strict_types=1);

namespace App\Kitchen\Application\GetRecipe;

use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\RecipeId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetRecipeHandler
{
    public function __construct(
        private readonly RecipeRepository $recipes,
    ) {
    }

    public function __invoke(GetRecipe $query): RecipeView
    {
        $recipe = $this->recipes->findById(new RecipeId($query->recipeId));

        if ($recipe === null) {
            throw RecipeNotFoundException::withId($query->recipeId);
        }

        return $this->toView($recipe);
    }

    private function toView(Recipe $recipe): RecipeView
    {
        return new RecipeView(
            id: $recipe->id()->value(),
            name: $recipe->name()->value(),
            priceAmount: $recipe->price()->amount(),
            priceCurrency: $recipe->price()->currency(),
            cookingTimeMinutes: $recipe->cookingTimeMinutes(),
            ingredients: array_map(
                static fn (IngredientRequirement $i): array => [
                    'name' => $i->name(),
                    'quantity' => $i->quantity(),
                    'unit' => $i->unit()->value,
                ],
                $recipe->ingredients(),
            ),
        );
    }
}
