<?php

declare(strict_types=1);

namespace App\Kitchen\Application\CreateRecipe;

use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Application\Exception\RecipeAlreadyExistsException;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateRecipeHandler
{
    public function __construct(
        private readonly RecipeRepository $recipes,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(CreateRecipe $command): void
    {
        if ($this->recipes->findByName(new RecipeName($command->name)) !== null) {
            throw RecipeAlreadyExistsException::withName($command->name);
        }

        $ingredients = array_map(
            static fn (array $i): IngredientRequirement => new IngredientRequirement(
                $i['name'],
                (float) $i['quantity'],
                Unit::from($i['unit']),
            ),
            $command->ingredients,
        );

        $recipe = Recipe::create(
            new RecipeId($command->id),
            new RecipeName($command->name),
            $ingredients,
            new Money($command->priceAmount, $command->priceCurrency),
            $command->cookingTimeMinutes,
        );

        $this->recipes->save($recipe);
        $this->eventBus->publish(...$recipe->pullDomainEvents());
    }
}
