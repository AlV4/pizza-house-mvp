<?php

declare(strict_types=1);

namespace App\Kitchen\Infrastructure\Persistence\Doctrine;

use App\Kitchen\Application\GetRecipe\RecipeView;
use App\Kitchen\Application\ListRecipes\RecipeListPort;
use Doctrine\DBAL\Connection;

final class DoctrineRecipeListPort implements RecipeListPort
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<RecipeView>
     */
    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, price_amount, price_currency, cooking_time_minutes, ingredients
             FROM kitchen_recipes
             ORDER BY name ASC'
        );

        return array_map(
            static fn (array $row): RecipeView => new RecipeView(
                id: $row['id'],
                name: $row['name'],
                priceAmount: (int) $row['price_amount'],
                priceCurrency: $row['price_currency'],
                cookingTimeMinutes: (int) $row['cooking_time_minutes'],
                ingredients: json_decode($row['ingredients'], true, flags: JSON_THROW_ON_ERROR),
            ),
            $rows,
        );
    }
}
