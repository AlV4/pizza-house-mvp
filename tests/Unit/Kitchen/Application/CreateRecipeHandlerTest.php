<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\CreateRecipe\CreateRecipe;
use App\Kitchen\Application\CreateRecipe\CreateRecipeHandler;
use App\Kitchen\Application\Exception\RecipeAlreadyExistsException;
use App\Kitchen\Domain\Aggregate\Recipe;
use App\Kitchen\Domain\Event\RecipeCreated;
use App\Kitchen\Domain\Repository\RecipeRepository;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\Exception\RecipeMustHaveAtLeastOneIngredient;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Kitchen\Domain\ValueObject\Unit;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateRecipeHandlerTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    private RecipeRepository&MockObject $recipes;
    private EventBus&MockObject $eventBus;
    private CreateRecipeHandler $handler;

    protected function setUp(): void
    {
        $this->recipes  = $this->createMock(RecipeRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->handler  = new CreateRecipeHandler($this->recipes, $this->eventBus);
    }

    public function test_saves_recipe_and_publishes_recipe_created_event_on_happy_path(): void
    {
        $this->recipes->method('findByName')->willReturn(null);
        $this->recipes->expects(self::once())->method('save');

        $publishedEvents = [];
        $this->eventBus
            ->expects(self::once())
            ->method('publish')
            ->willReturnCallback(static function (DomainEvent ...$events) use (&$publishedEvents): void {
                $publishedEvents = $events;
            });

        ($this->handler)($this->aCreateRecipeCommand());

        self::assertCount(1, $publishedEvents);
        self::assertInstanceOf(RecipeCreated::class, $publishedEvents[0]);
        self::assertSame(self::VALID_ULID, $publishedEvents[0]->recipeId);
        self::assertSame('Margherita', $publishedEvents[0]->name);
    }

    public function test_throws_domain_exception_when_recipe_name_already_exists(): void
    {
        $this->recipes
            ->method('findByName')
            ->willReturn($this->anExistingRecipe());

        $this->recipes->expects(self::never())->method('save');
        $this->eventBus->expects(self::never())->method('publish');

        $this->expectException(RecipeAlreadyExistsException::class);
        $this->expectExceptionMessage('Margherita');

        ($this->handler)($this->aCreateRecipeCommand());
    }

    public function test_propagates_domain_exception_when_ingredient_list_is_empty(): void
    {
        $this->recipes->method('findByName')->willReturn(null);

        $this->recipes->expects(self::never())->method('save');
        $this->eventBus->expects(self::never())->method('publish');

        $this->expectException(RecipeMustHaveAtLeastOneIngredient::class);

        ($this->handler)(new CreateRecipe(
            id: self::VALID_ULID,
            name: 'Margherita',
            ingredients: [],
            priceAmount: 1299,
            priceCurrency: 'EUR',
            cookingTimeMinutes: 15,
        ));
    }

    private function aCreateRecipeCommand(): CreateRecipe
    {
        return new CreateRecipe(
            id: self::VALID_ULID,
            name: 'Margherita',
            ingredients: [['name' => 'Mozzarella', 'quantity' => 100.0, 'unit' => 'g']],
            priceAmount: 1299,
            priceCurrency: 'EUR',
            cookingTimeMinutes: 15,
        );
    }

    private function anExistingRecipe(): Recipe
    {
        return Recipe::create(
            new RecipeId(self::VALID_ULID),
            new RecipeName('Margherita'),
            [new IngredientRequirement('Mozzarella', 100.0, Unit::Gram)],
            new Money(1299, 'EUR'),
            15,
        );
    }
}
