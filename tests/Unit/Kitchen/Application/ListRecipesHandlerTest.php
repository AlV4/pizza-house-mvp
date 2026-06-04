<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Application;

use App\Kitchen\Application\GetRecipe\RecipeView;
use App\Kitchen\Application\ListRecipes\ListRecipes;
use App\Kitchen\Application\ListRecipes\ListRecipesHandler;
use App\Kitchen\Application\ListRecipes\RecipeListPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListRecipesHandlerTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    private RecipeListPort&MockObject $port;
    private ListRecipesHandler $handler;

    protected function setUp(): void
    {
        $this->port    = $this->createMock(RecipeListPort::class);
        $this->handler = new ListRecipesHandler($this->port);
    }

    public function test_delegates_to_port_and_returns_its_result_unchanged(): void
    {
        $expected = [$this->aRecipeView()];

        $this->port->expects(self::once())->method('all')->willReturn($expected);

        $result = ($this->handler)(new ListRecipes());

        self::assertSame($expected, $result);
    }

    public function test_returns_empty_array_when_port_has_no_recipes(): void
    {
        $this->port->method('all')->willReturn([]);

        $result = ($this->handler)(new ListRecipes());

        self::assertSame([], $result);
    }

    private function aRecipeView(): RecipeView
    {
        return new RecipeView(
            id: self::VALID_ULID,
            name: 'Margherita',
            priceAmount: 1299,
            priceCurrency: 'EUR',
            cookingTimeMinutes: 15,
            ingredients: [['name' => 'Mozzarella', 'quantity' => 100.0, 'unit' => 'g']],
        );
    }
}
