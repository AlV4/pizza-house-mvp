<?php

declare(strict_types=1);

namespace App\Tests\Integration\Kitchen;

use App\Kitchen\Application\CreateRecipe\CreateRecipe;
use App\Kitchen\Application\Exception\RecipeAlreadyExistsException;
use App\Kitchen\Application\GetRecipe\GetRecipe;
use App\Kitchen\Application\GetRecipe\RecipeView;
use App\Kitchen\Domain\Event\RecipeCreated;
use App\Kitchen\Domain\Exception\RecipeMustHaveAtLeastOneIngredient;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Uid\Ulid;

final class CreateRecipeFlowTest extends IntegrationTestCase
{
    public function test_recipe_is_created_and_event_is_published_when_input_is_valid(): void
    {
        $recipeId = (string) new Ulid();

        $this->commandBus->dispatch(new CreateRecipe(
            id: $recipeId,
            name: 'Margherita',
            ingredients: [
                ['name' => 'Mozzarella', 'quantity' => 150.0, 'unit' => 'g'],
                ['name' => 'Tomato Sauce', 'quantity' => 80.0, 'unit' => 'g'],
            ],
            priceAmount: 1200,
            priceCurrency: 'USD',
            cookingTimeMinutes: 12,
        ));

        $view = $this->queryBus->ask(new GetRecipe($recipeId));
        self::assertInstanceOf(RecipeView::class, $view);
        self::assertSame($recipeId, $view->id);
        self::assertSame('Margherita', $view->name);
        self::assertSame(1200, $view->priceAmount);
        self::assertSame('USD', $view->priceCurrency);
        self::assertCount(2, $view->ingredients);

        $events = $this->recordedEvents->ofType(RecipeCreated::class);
        self::assertCount(1, $events);
        self::assertSame($recipeId, $events[0]->recipeId);
        self::assertSame('Margherita', $events[0]->name);
        self::assertSame(1200, $events[0]->priceAmount);
        self::assertSame('USD', $events[0]->priceCurrency);
    }

    public function test_creating_a_recipe_with_a_duplicate_name_is_rejected(): void
    {
        $this->commandBus->dispatch($this->validRecipeCommand(name: 'Pepperoni'));

        $this->expectException(RecipeAlreadyExistsException::class);

        $this->commandBus->dispatch($this->validRecipeCommand(name: 'Pepperoni'));
    }

    public function test_creating_a_recipe_with_no_ingredients_is_rejected(): void
    {
        $command = new CreateRecipe(
            id: (string) new Ulid(),
            name: 'Empty Pizza',
            ingredients: [],
            priceAmount: 900,
            priceCurrency: 'USD',
            cookingTimeMinutes: 10,
        );

        $this->expectException(RecipeMustHaveAtLeastOneIngredient::class);

        $this->commandBus->dispatch($command);
    }

    public function test_creating_a_recipe_with_a_non_positive_price_is_rejected(): void
    {
        $command = new CreateRecipe(
            id: (string) new Ulid(),
            name: 'Free Pizza',
            ingredients: [
                ['name' => 'Dough', 'quantity' => 200.0, 'unit' => 'g'],
            ],
            priceAmount: 0,
            priceCurrency: 'USD',
            cookingTimeMinutes: 10,
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->commandBus->dispatch($command);
    }

    private function validRecipeCommand(string $name): CreateRecipe
    {
        return new CreateRecipe(
            id: (string) new Ulid(),
            name: $name,
            ingredients: [
                ['name' => 'Dough', 'quantity' => 200.0, 'unit' => 'g'],
                ['name' => 'Pepperoni', 'quantity' => 60.0, 'unit' => 'g'],
            ],
            priceAmount: 1400,
            priceCurrency: 'USD',
            cookingTimeMinutes: 14,
        );
    }
}
