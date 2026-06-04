<?php

declare(strict_types=1);

namespace App\Kitchen\Application\AddIngredientToRecipe;

use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\Unit;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class AddIngredientToRecipeHandler
{
    public function __construct(
        private readonly RecipeRepository $recipes,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(AddIngredientToRecipe $command): void
    {
        $recipe = $this->recipes->findById(new RecipeId($command->recipeId));

        if ($recipe === null) {
            throw RecipeNotFoundException::withId($command->recipeId);
        }

        $recipe->addIngredient(new IngredientRequirement(
            $command->name,
            $command->quantity,
            Unit::from($command->unit),
        ));

        $this->recipes->save($recipe);
        $this->eventBus->publish(...$recipe->pullDomainEvents());
    }
}
