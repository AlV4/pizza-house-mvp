<?php

declare(strict_types=1);

namespace App\Kitchen\Application\ChangeRecipePrice;

use App\Kitchen\Application\Exception\RecipeNotFoundException;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeRecipePriceHandler
{
    public function __construct(
        private readonly RecipeRepository $recipes,
        private readonly EventBus $eventBus,
    ) {
    }

    public function __invoke(ChangeRecipePrice $command): void
    {
        $recipe = $this->recipes->findById(new RecipeId($command->recipeId));

        if ($recipe === null) {
            throw RecipeNotFoundException::withId($command->recipeId);
        }

        $recipe->changePrice(new Money($command->newAmount, $command->currency));

        $this->recipes->save($recipe);
        $this->eventBus->publish(...$recipe->pullDomainEvents());
    }
}
